<?php

/*
	FusionPBX - ERPNext / Frappe Integration
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Caller-ID name lookup. Returns the contact name cached from ERPNext for a
	given phone number. Can be used as a FreeSWITCH CID name lookup source.

	Auth: X-Fusionpbx-Secret header must match erpnext.shared_secret.

	GET /app/erpnext_integration/api/lookup.php?number=15551234567&domain_uuid=...
	Response: {"found":true,"name":"Acme Corp","doctype":"Customer","docname":"Acme Corp"}
*/

	require_once dirname(__DIR__, 3) . "/resources/require.php";
	require_once dirname(__DIR__) . "/resources/classes/erpnext.php";

	header('Content-Type: application/json');

	$database = new database;

	$number = $_GET['number'] ?? '';
	$domain_uuid = $_GET['domain_uuid'] ?? '';
	$domain_name = $_GET['domain_name'] ?? ($_SERVER['HTTP_HOST'] ?? '');

	if (!is_uuid($domain_uuid) && $domain_name !== '') {
		$sql = "select domain_uuid from v_domains where domain_name = :name";
		$domain_uuid = $database->select($sql, ['name' => $domain_name], 'column');
		unset($sql);
	}

	if (!is_uuid($domain_uuid) || $number === '') {
		http_response_code(400);
		echo json_encode(['found' => false, 'error' => 'missing_parameters']);
		exit;
	}

	//authenticate
	$erpnext = new erpnext($domain_uuid);
	$expected = $erpnext->get('shared_secret', '');
	$provided = $_SERVER['HTTP_X_FUSIONPBX_SECRET'] ?? ($_GET['shared_secret'] ?? '');
	if ($expected === '' || !hash_equals($expected, (string)$provided)) {
		http_response_code(403);
		echo json_encode(['found' => false, 'error' => 'invalid_secret']);
		exit;
	}

	$key = erpnext::normalize_number($number);

	//look up in the local contact cache (fast, populated by the sync worker)
	$sql  = "select contact_name, doctype, erpnext_name from v_erpnext_contacts ";
	$sql .= "where domain_uuid = :domain_uuid and phone_number = :phone limit 1";
	$parameters = ['domain_uuid' => $domain_uuid, 'phone' => $key];
	$row = $database->select($sql, $parameters, 'row');
	unset($sql, $parameters);

	//fall back to a live ERPNext lookup if not cached and integration is enabled
	if (empty($row) && $erpnext->is_enabled()) {
		$live = $erpnext->lookup_contact($number);
		if (!empty($live['name'])) {
			$row = [
				'contact_name' => $live['name'],
				'doctype'      => $live['doctype'] ?? '',
				'erpnext_name' => $live['docname'] ?? '',
			];
			//cache it for next time
			$sql  = "insert into v_erpnext_contacts (erpnext_contact_uuid, domain_uuid, phone_number, contact_name, doctype, erpnext_name, insert_date) ";
			$sql .= "values (:uuid, :domain_uuid, :phone, :name, :doctype, :docname, now())";
			$parameters = [
				'uuid' => uuid(), 'domain_uuid' => $domain_uuid, 'phone' => $key,
				'name' => $row['contact_name'], 'doctype' => $row['doctype'], 'docname' => $row['erpnext_name'],
			];
			$database->execute($sql, $parameters);
			unset($sql, $parameters);
		}
	}

	if (empty($row)) {
		echo json_encode(['found' => false]);
		exit;
	}

	echo json_encode([
		'found'   => true,
		'name'    => $row['contact_name'],
		'doctype' => $row['doctype'],
		'docname' => $row['erpnext_name'],
	]);
?>
