<?php

/*
	FusionPBX - ERPNext / Frappe Integration
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Contact sync worker.

	Pulls Contacts / Leads / Customers with phone numbers from ERPNext (via the
	companion Frappe app's fusionpbx_integration.api.export_contacts method) and
	caches them in v_erpnext_contacts for fast caller-ID name lookup.

	Recommended cron (every 15 minutes):
		*/15 * * * * www-data php /var/www/fusionpbx/app/erpnext_integration/resources/cron/erpnext_contact_sync.php >/dev/null 2>&1
*/

	set_include_path('/var/www/fusionpbx');
	require_once '/var/www/fusionpbx/resources/require.php';
	require_once '/var/www/fusionpbx/app/erpnext_integration/resources/classes/erpnext.php';

	if (php_sapi_name() !== 'cli') { echo "CLI only\n"; exit(1); }

	$database = new database;

	//find enabled domains
	$sql  = "select domain_uuid from v_domain_settings ";
	$sql .= "where domain_setting_category='erpnext' and domain_setting_subcategory='enabled' ";
	$sql .= "and domain_setting_value='true' and domain_setting_enabled='true'";
	$domains = $database->select($sql, null, 'all') ?: [];
	unset($sql);

	$total = 0;
	foreach ($domains as $d) {
		$domain_uuid = $d['domain_uuid'];
		$erpnext = new erpnext($domain_uuid);
		if (!$erpnext->is_enabled()) continue;

		//paginate through the ERPNext export
		$page = 0;
		$page_size = 500;
		do {
			$path = '/api/method/fusionpbx_integration.api.export_contacts?limit=' . $page_size . '&offset=' . ($page * $page_size);
			$result = $erpnext->request('GET', $path);
			if ($result === false) {
				fwrite(STDERR, "contact_sync[$domain_uuid]: " . $erpnext->last_error . "\n");
				break;
			}
			$contacts = $result['message'] ?? [];
			if (empty($contacts)) break;

			foreach ($contacts as $c) {
				$phone = erpnext::normalize_number($c['phone'] ?? '');
				if ($phone === '') continue;
				$total += upsert_contact($database, $domain_uuid, $phone, $c);
			}
			$page++;
		} while (count($contacts) === $page_size && $page < 100);
	}

	echo "erpnext_contact_sync: upserted {$total} contact(s)\n";
	exit(0);


function upsert_contact($database, $domain_uuid, $phone, $c) {
	$name    = $c['name'] ?? '';
	$doctype = $c['doctype'] ?? '';
	$docname = $c['docname'] ?? '';

	$sql = "select erpnext_contact_uuid from v_erpnext_contacts where domain_uuid = :domain_uuid and phone_number = :phone and coalesce(erpnext_name,'') = :docname limit 1";
	$parameters = ['domain_uuid' => $domain_uuid, 'phone' => $phone, 'docname' => $docname];
	$existing = $database->select($sql, $parameters, 'column');
	unset($sql, $parameters);

	if ($existing) {
		$sql = "update v_erpnext_contacts set contact_name = :name, doctype = :doctype, update_date = now() where erpnext_contact_uuid = :uuid";
		$parameters = ['name' => $name, 'doctype' => $doctype, 'uuid' => $existing];
	} else {
		$sql = "insert into v_erpnext_contacts (erpnext_contact_uuid, domain_uuid, phone_number, contact_name, doctype, erpnext_name, insert_date) values (:uuid, :domain_uuid, :phone, :name, :doctype, :docname, now())";
		$parameters = ['uuid' => uuid(), 'domain_uuid' => $domain_uuid, 'phone' => $phone, 'name' => $name, 'doctype' => $doctype, 'docname' => $docname];
	}
	$database->execute($sql, $parameters);
	unset($sql, $parameters);
	return 1;
}
?>
