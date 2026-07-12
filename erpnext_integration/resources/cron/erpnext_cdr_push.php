<?php

/*
	FusionPBX - ERPNext / Frappe Integration
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	CDR push worker.

	Runs periodically (cron / FusionPBX task scheduler). For every domain that has
	the integration enabled and push_cdr = true it:
	  1. enqueues new completed calls from v_xml_cdr into v_erpnext_cdr_queue
	  2. drains pending queue rows, creating a Call Log doc in ERPNext for each

	Invoke:
		php /var/www/fusionpbx/app/erpnext_integration/resources/cron/erpnext_cdr_push.php

	Recommended cron (every minute):
		* * * * * www-data php /var/www/fusionpbx/app/erpnext_integration/resources/cron/erpnext_cdr_push.php >/dev/null 2>&1
*/

//bootstrap FusionPBX
	set_include_path('/var/www/fusionpbx');
	$document_root = '/var/www/fusionpbx';
	require_once $document_root . '/resources/require.php';
	require_once $document_root . '/app/erpnext_integration/resources/classes/erpnext.php';

	//run from CLI only
	if (php_sapi_name() !== 'cli') {
		echo "CLI only\n";
		exit(1);
	}

	//grant the permissions this worker needs
	$_SESSION['user_uuid'] = $_SESSION['user_uuid'] ?? '00000000-0000-0000-0000-000000000000';

	$database = new database;

//find domains with the integration enabled
	$sql = "select ds.domain_setting_value as enabled, ds.domain_uuid ";
	$sql .= "from v_domain_settings ds ";
	$sql .= "where ds.domain_setting_category = 'erpnext' and ds.domain_setting_subcategory = 'enabled' ";
	$sql .= "and ds.domain_setting_value = 'true' and ds.domain_setting_enabled = 'true'";
	$domains = $database->select($sql, null, 'all') ?: [];
	unset($sql);

	//also allow a global (default) enable with per-domain credentials
	if (empty($domains)) {
		$sql = "select default_setting_value from v_default_settings where default_setting_category='erpnext' and default_setting_subcategory='enabled' and default_setting_value='true' and default_setting_enabled='true'";
		if ($database->select($sql, null, 'column') === 'true') {
			$sql = "select domain_uuid from v_domains where domain_enabled = 'true'";
			foreach (($database->select($sql, null, 'all') ?: []) as $d) {
				$domains[] = ['domain_uuid' => $d['domain_uuid']];
			}
		}
		unset($sql);
	}

	$total_sent = 0;
	foreach ($domains as $d) {
		$domain_uuid = $d['domain_uuid'];
		$erpnext = new erpnext($domain_uuid);

		if (!$erpnext->is_enabled() || $erpnext->get('push_cdr', 'true') !== 'true') {
			continue;
		}

		enqueue_new_cdrs($database, $domain_uuid);
		$total_sent += drain_queue($database, $erpnext, $domain_uuid);
	}

	echo "erpnext_cdr_push: sent {$total_sent} call log(s)\n";
	exit(0);


/**
 * Copy completed CDRs that are not yet queued into v_erpnext_cdr_queue.
 * Only picks up calls from the last 2 days to avoid backfilling history on first run.
 */
function enqueue_new_cdrs($database, $domain_uuid) {
	$sql  = "select c.xml_cdr_uuid, c.direction, c.caller_id_number, c.destination_number, ";
	$sql .= "c.start_stamp, c.end_stamp, c.duration, c.hangup_cause, c.record_path, c.record_name ";
	$sql .= "from v_xml_cdr c ";
	$sql .= "left join v_erpnext_cdr_queue q on q.xml_cdr_uuid = c.xml_cdr_uuid ";
	$sql .= "where c.domain_uuid = :domain_uuid ";
	$sql .= "and q.xml_cdr_uuid is null ";
	$sql .= "and c.start_stamp > (now() - interval '2 days') ";
	$sql .= "and c.status is distinct from 'processing' ";
	$sql .= "order by c.start_stamp asc limit 200";
	$parameters['domain_uuid'] = $domain_uuid;
	$rows = $database->select($sql, $parameters, 'all') ?: [];
	unset($sql, $parameters);

	foreach ($rows as $c) {
		$sql  = "insert into v_erpnext_cdr_queue ";
		$sql .= "(erpnext_cdr_queue_uuid, domain_uuid, xml_cdr_uuid, direction, caller_id_number, destination_number, start_stamp, end_stamp, duration, hangup_cause, status, insert_date) ";
		$sql .= "values (:uuid, :domain_uuid, :xml_cdr_uuid, :direction, :caller, :dest, :start_stamp, :end_stamp, :duration, :hangup, 'pending', now()) ";
		$sql .= "on conflict (xml_cdr_uuid) do nothing";
		$parameters = [
			'uuid'          => uuid(),
			'domain_uuid'   => $domain_uuid,
			'xml_cdr_uuid'  => $c['xml_cdr_uuid'],
			'direction'     => $c['direction'],
			'caller'        => $c['caller_id_number'],
			'dest'          => $c['destination_number'],
			'start_stamp'   => $c['start_stamp'],
			'end_stamp'     => $c['end_stamp'],
			'duration'      => (int)$c['duration'],
			'hangup'        => $c['hangup_cause'],
		];
		$database->execute($sql, $parameters);
		unset($sql, $parameters);
	}
}

/**
 * Push pending queue rows to ERPNext. Returns the number successfully sent.
 */
function drain_queue($database, $erpnext, $domain_uuid) {
	$sql = "select * from v_erpnext_cdr_queue where domain_uuid = :domain_uuid and status = 'pending' and attempts < 5 order by start_stamp asc limit 100";
	$parameters['domain_uuid'] = $domain_uuid;
	$queue = $database->select($sql, $parameters, 'all') ?: [];
	unset($sql, $parameters);

	$recording_base = $erpnext->get('recording_base_url', '');
	$sent = 0;

	foreach ($queue as $row) {
		//map FusionPBX direction to ERPNext Call Log type (Incoming/Outgoing)
		$type = (strtolower((string)$row['direction']) === 'inbound') ? 'Incoming' : 'Outgoing';

		//map hangup cause to a coarse Call Log status
		$status = map_status($row['hangup_cause'], (int)$row['duration']);

		$recording_url = '';
		if ($recording_base !== '') {
			$recording_url = $recording_base . rawurlencode($row['xml_cdr_uuid']);
		}

		$call = [
			'id'        => $row['xml_cdr_uuid'],                 // idempotent: reusing the CDR uuid dedupes in ERPNext
			'from'      => $row['caller_id_number'],
			'to'        => $row['destination_number'],
			'type'      => $type,
			'status'    => $status,
			'duration'  => (int)$row['duration'],
			'medium'    => 'FusionPBX',
		];
		if (!empty($row['start_stamp'])) $call['start_time'] = date('Y-m-d H:i:s', strtotime($row['start_stamp']));
		if (!empty($row['end_stamp']))   $call['end_time']   = date('Y-m-d H:i:s', strtotime($row['end_stamp']));
		if ($recording_url !== '')       $call['recording_url'] = $recording_url;

		$docname = $erpnext->create_call_log($call);

		if ($docname !== false) {
			$sql = "update v_erpnext_cdr_queue set status = 'sent', erpnext_call_log_id = :docname, recording_url = :rec, update_date = now(), attempts = attempts + 1 where erpnext_cdr_queue_uuid = :uuid";
			$parameters = ['docname' => is_string($docname) ? $docname : $row['xml_cdr_uuid'], 'rec' => $recording_url, 'uuid' => $row['erpnext_cdr_queue_uuid']];
			$database->execute($sql, $parameters);
			unset($sql, $parameters);
			$sent++;
		} else {
			//ERPNext returns 409 DuplicateEntryError if the Call Log already exists -> treat as sent
			if ($erpnext->last_http_code === 409 || stripos($erpnext->last_error, 'Duplicate') !== false) {
				$sql = "update v_erpnext_cdr_queue set status = 'sent', erpnext_call_log_id = :docname, update_date = now(), attempts = attempts + 1 where erpnext_cdr_queue_uuid = :uuid";
				$parameters = ['docname' => $row['xml_cdr_uuid'], 'uuid' => $row['erpnext_cdr_queue_uuid']];
			} else {
				$new_status = ((int)$row['attempts'] + 1) >= 5 ? 'failed' : 'pending';
				$sql = "update v_erpnext_cdr_queue set status = :status, last_error = :err, update_date = now(), attempts = attempts + 1 where erpnext_cdr_queue_uuid = :uuid";
				$parameters = ['status' => $new_status, 'err' => substr($erpnext->last_error, 0, 500), 'uuid' => $row['erpnext_cdr_queue_uuid']];
			}
			$database->execute($sql, $parameters);
			unset($sql, $parameters);
		}
	}
	return $sent;
}

/**
 * Map a FreeSWITCH hangup cause + duration to an ERPNext Call Log status.
 * ERPNext Call Log status options: Ringing, In Progress, Completed, Failed,
 * Busy, No Answer, Queued, Canceled.
 */
function map_status($hangup_cause, $duration) {
	$hangup_cause = strtoupper((string)$hangup_cause);
	switch ($hangup_cause) {
		case 'NORMAL_CLEARING':
			return $duration > 0 ? 'Completed' : 'No Answer';
		case 'USER_BUSY':
			return 'Busy';
		case 'NO_ANSWER':
		case 'NO_USER_RESPONSE':
		case 'ALLOTTED_TIMEOUT':
			return 'No Answer';
		case 'ORIGINATOR_CANCEL':
		case 'LOSE_RACE':
			return 'Canceled';
		case 'CALL_REJECTED':
		case 'NORMAL_TEMPORARY_FAILURE':
		case 'RECOVERY_ON_TIMER_EXPIRE':
			return 'Failed';
		default:
			return $duration > 0 ? 'Completed' : 'Failed';
	}
}

?>
