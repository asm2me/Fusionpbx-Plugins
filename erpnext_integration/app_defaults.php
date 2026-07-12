<?php

/*
	FusionPBX - ERPNext / Frappe Integration
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Database schema and default data.
*/

if ($domains_processed == 1) {

	//default settings
	$y = 0;
	$defaults = [
		['d7f1a2b3-0001-0001-0001-1b2c3d4e5f60', 'enabled',            'boolean', 'false', 'Enable or disable the ERPNext integration.'],
		['d7f1a2b3-0001-0002-0001-1b2c3d4e5f60', 'url',                'text',    '',      'Base URL of the ERPNext site, e.g. https://erp.example.com'],
		['d7f1a2b3-0001-0003-0001-1b2c3d4e5f60', 'api_key',            'text',    '',      'ERPNext API key.'],
		['d7f1a2b3-0001-0004-0001-1b2c3d4e5f60', 'api_secret',         'text',    '',      'ERPNext API secret.'],
		['d7f1a2b3-0001-0005-0001-1b2c3d4e5f60', 'shared_secret',      'text',    '',      'Shared secret ERPNext sends in X-Fusionpbx-Secret.'],
		['d7f1a2b3-0001-0006-0001-1b2c3d4e5f60', 'push_cdr',           'boolean', 'true',  'Push completed call records to ERPNext.'],
		['d7f1a2b3-0001-0007-0001-1b2c3d4e5f60', 'recording_base_url', 'text',    '',      'Public base URL for recording links.'],
		['d7f1a2b3-0001-0008-0001-1b2c3d4e5f60', 'screen_pop',         'boolean', 'true',  'Notify ERPNext of inbound calls for screen-pop.'],
		['d7f1a2b3-0001-0009-0001-1b2c3d4e5f60', 'verify_tls',         'boolean', 'true',  'Verify the ERPNext TLS certificate.'],
	];
	foreach ($defaults as $d) {
		$array['default_settings'][$y]['default_setting_uuid'] = $d[0];
		$array['default_settings'][$y]['default_setting_category'] = 'erpnext';
		$array['default_settings'][$y]['default_setting_subcategory'] = $d[1];
		$array['default_settings'][$y]['default_setting_name'] = $d[2];
		$array['default_settings'][$y]['default_setting_value'] = $d[3];
		$array['default_settings'][$y]['default_setting_enabled'] = 'true';
		$array['default_settings'][$y]['default_setting_description'] = $d[4];
		$y++;
	}

	$p = new permissions;
	$p->add("default_setting_add", "temp");
	$p->add("default_setting_edit", "temp");

	$database = new database;
	$database->app_name = "erpnext_integration";
	$database->app_uuid = "d7f1a2b3-c4d5-6e7f-8a90-1b2c3d4e5f60";
	$database->save($array);
	unset($array);

	$p->delete("default_setting_add", "temp");
	$p->delete("default_setting_edit", "temp");

	//outbound sync queue: one row per call we attempt to push to ERPNext
	$sql  = "CREATE TABLE IF NOT EXISTS v_erpnext_cdr_queue ( ";
	$sql .= "erpnext_cdr_queue_uuid uuid PRIMARY KEY, ";
	$sql .= "domain_uuid uuid NOT NULL, ";
	$sql .= "xml_cdr_uuid uuid NOT NULL, ";
	$sql .= "direction varchar(20), ";
	$sql .= "caller_id_number varchar(64), ";
	$sql .= "destination_number varchar(64), ";
	$sql .= "start_stamp timestamptz, ";
	$sql .= "end_stamp timestamptz, ";
	$sql .= "duration integer, ";
	$sql .= "hangup_cause varchar(64), ";
	$sql .= "recording_url text, ";
	$sql .= "status varchar(20) NOT NULL DEFAULT 'pending', ";  // pending | sent | failed
	$sql .= "attempts integer NOT NULL DEFAULT 0, ";
	$sql .= "last_error text, ";
	$sql .= "erpnext_call_log_id varchar(140), ";
	$sql .= "insert_date timestamptz DEFAULT now(), ";
	$sql .= "update_date timestamptz ";
	$sql .= ") ";
	$database = new database;
	$database->execute($sql);
	unset($sql);

	$sql = "CREATE UNIQUE INDEX IF NOT EXISTS idx_erpnext_cdr_queue_cdr ON v_erpnext_cdr_queue (xml_cdr_uuid)";
	$database->execute($sql);
	unset($sql);

	$sql = "CREATE INDEX IF NOT EXISTS idx_erpnext_cdr_queue_status ON v_erpnext_cdr_queue (domain_uuid, status)";
	$database->execute($sql);
	unset($sql);

	//contact cache: contacts pulled from ERPNext for caller-ID name lookup
	$sql  = "CREATE TABLE IF NOT EXISTS v_erpnext_contacts ( ";
	$sql .= "erpnext_contact_uuid uuid PRIMARY KEY, ";
	$sql .= "domain_uuid uuid NOT NULL, ";
	$sql .= "phone_number varchar(64) NOT NULL, ";   // normalized (digits only, trailing significant digits)
	$sql .= "contact_name varchar(255), ";
	$sql .= "doctype varchar(64), ";                 // Contact | Lead | Customer
	$sql .= "erpnext_name varchar(140), ";           // ERPNext docname for screen-pop deep link
	$sql .= "insert_date timestamptz DEFAULT now(), ";
	$sql .= "update_date timestamptz ";
	$sql .= ") ";
	$database->execute($sql);
	unset($sql);

	$sql = "CREATE INDEX IF NOT EXISTS idx_erpnext_contacts_phone ON v_erpnext_contacts (domain_uuid, phone_number)";
	$database->execute($sql);
	unset($sql);
}

?>
