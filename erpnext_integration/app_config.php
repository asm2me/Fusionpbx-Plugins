<?php

/*
	FusionPBX - ERPNext / Frappe Integration
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Bidirectional integration between FusionPBX and ERPNext / Frappe:
	  - CDR push        : completed calls are posted to ERPNext as Call Log docs
	  - Screen-pop      : inbound calls trigger a screen-pop of the matching Contact/Lead
	  - Click-to-dial   : ERPNext originates calls through FusionPBX
	  - Contact sync    : ERPNext contacts are cached for caller-ID name lookup
	  - Recording links : recording URLs are attached to the ERPNext Call Log
*/

	//application details
	$apps[$x]['name'] = "ERPNext Integration";
	$apps[$x]['uuid'] = "d7f1a2b3-c4d5-6e7f-8a90-1b2c3d4e5f60";
	$apps[$x]['category'] = "Integration";
	$apps[$x]['subcategory'] = "";
	$apps[$x]['version'] = "1.0.0";
	$apps[$x]['license'] = "Mozilla Public License 1.1";
	$apps[$x]['url'] = "https://voipegypt.com";
	$apps[$x]['description']['en-us'] = "Bidirectional integration between FusionPBX and ERPNext/Frappe: CDR sync as Call Logs, inbound screen-pop, click-to-dial, contact sync and recording links.";

	//permission groups
	$y = 0;
	$apps[$x]['permissions'][$y]['name'] = "erpnext_integration_view";
	$apps[$x]['permissions'][$y]['menu']['uuid'] = "d7f1a2b3-a001-0001-0001-1b2c3d4e5f60";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$y++;

	$apps[$x]['permissions'][$y]['name'] = "erpnext_integration_edit";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$y++;

	//used by ERPNext to authenticate to the inbound endpoints (originate / lookup)
	$apps[$x]['permissions'][$y]['name'] = "erpnext_integration_api";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$apps[$x]['permissions'][$y]['groups'][] = "user";

	//default settings
	$y = 0;
	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "d7f1a2b3-0001-0001-0001-1b2c3d4e5f60";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "erpnext";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "enabled";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "false";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Enable or disable the ERPNext integration.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "d7f1a2b3-0001-0002-0001-1b2c3d4e5f60";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "erpnext";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "url";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Base URL of the ERPNext site, e.g. https://erp.example.com";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "d7f1a2b3-0001-0003-0001-1b2c3d4e5f60";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "erpnext";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "api_key";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "ERPNext API key (from User > API Access).";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "d7f1a2b3-0001-0004-0001-1b2c3d4e5f60";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "erpnext";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "api_secret";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "ERPNext API secret paired with the API key.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "d7f1a2b3-0001-0005-0001-1b2c3d4e5f60";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "erpnext";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "shared_secret";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Shared secret ERPNext must send in X-Fusionpbx-Secret when calling FusionPBX endpoints (originate / lookup).";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "d7f1a2b3-0001-0006-0001-1b2c3d4e5f60";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "erpnext";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "push_cdr";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Push completed call records to ERPNext as Call Log documents.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "d7f1a2b3-0001-0007-0001-1b2c3d4e5f60";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "erpnext";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "recording_base_url";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Public base URL used to build recording links sent to ERPNext, e.g. https://pbx.example.com/app/xml_cdr/xml_cdr_download.php?id=";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "d7f1a2b3-0001-0008-0001-1b2c3d4e5f60";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "erpnext";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "screen_pop";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Notify ERPNext of inbound calls so agents get a screen-pop of the matching contact.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "d7f1a2b3-0001-0009-0001-1b2c3d4e5f60";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "erpnext";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "verify_tls";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Verify the ERPNext TLS certificate. Disable only for self-signed lab servers.";

	//menu items - Applications menu entry
	$y = 0;
	$apps[$x]['menu'][$y]['title']['en-us'] = "ERPNext Integration";
	$apps[$x]['menu'][$y]['uuid'] = "d7f1a2b3-a001-0001-0001-1b2c3d4e5f60";
	$apps[$x]['menu'][$y]['parent_uuid'] = "594d99c5-6128-9c88-ca35-4b33392cec0f"; //advanced menu
	$apps[$x]['menu'][$y]['category'] = "internal";
	$apps[$x]['menu'][$y]['icon'] = "fa-solid fa-plug";
	$apps[$x]['menu'][$y]['path'] = "/app/erpnext_integration/erpnext_integration.php";
	$apps[$x]['menu'][$y]['order'] = "";
	$apps[$x]['menu'][$y]['groups'][] = "superadmin";
	$apps[$x]['menu'][$y]['groups'][] = "admin";

?>
