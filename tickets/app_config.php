<?php

/*
	FusionPBX - Support Tickets
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1
*/

	//application details
	$apps[$x]['name'] = "Support Tickets";
	$apps[$x]['uuid'] = "a1b2c3d4-e5f6-7890-abcd-ef1234567890";
	$apps[$x]['category'] = "Switch";
	$apps[$x]['subcategory'] = "";
	$apps[$x]['version'] = "1.0.0";
	$apps[$x]['license'] = "Mozilla Public License 1.1";
	$apps[$x]['url'] = "https://www.fusionpbx.com";
	$apps[$x]['description']['en-us'] = "Support ticketing system with call-linked issue reporting from the web phone and mobile dialers.";

	//permission groups
	$y = 0;
	$apps[$x]['permissions'][$y]['name'] = "ticket_view";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$apps[$x]['permissions'][$y]['groups'][] = "user";
	$y++;

	$apps[$x]['permissions'][$y]['name'] = "ticket_add";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$apps[$x]['permissions'][$y]['groups'][] = "user";
	$y++;

	$apps[$x]['permissions'][$y]['name'] = "ticket_edit";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$y++;

	$apps[$x]['permissions'][$y]['name'] = "ticket_delete";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$y++;

	$apps[$x]['permissions'][$y]['name'] = "ticket_reply";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$apps[$x]['permissions'][$y]['groups'][] = "user";
	$y++;

	$apps[$x]['permissions'][$y]['name'] = "ticket_manage";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$y++;

	$apps[$x]['permissions'][$y]['name'] = "ticket_api";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$apps[$x]['permissions'][$y]['groups'][] = "user";

	//default settings
	$y = 0;
	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0001-0001-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "enabled";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Enable or disable the support tickets system.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0001-0002-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "webphone_report";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Allow users to report call issues directly from the web phone history.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0001-0003-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "auto_attach_log";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Automatically attach the web phone activity log when a ticket is created from call history.";
	$y++;

	//menu items
	$y = 0;
	$apps[$x]['menu'][$y]['title']['en-us'] = "Support Tickets";
	$apps[$x]['menu'][$y]['uuid'] = "a1b2c3d4-a001-0001-0001-ef1234567890";
	$apps[$x]['menu'][$y]['parent_uuid'] = "b4750c3f-2a86-b00d-b7d0-345c14eca286"; //applications menu
	$apps[$x]['menu'][$y]['category'] = "internal";
	$apps[$x]['menu'][$y]['icon'] = "fa-solid fa-ticket";
	$apps[$x]['menu'][$y]['path'] = "/app/tickets/tickets.php";
	$apps[$x]['menu'][$y]['order'] = "";
	$apps[$x]['menu'][$y]['groups'][] = "superadmin";
	$apps[$x]['menu'][$y]['groups'][] = "admin";
	$apps[$x]['menu'][$y]['groups'][] = "user";
	$y++;

	$apps[$x]['menu'][$y]['title']['en-us'] = "Support Tickets";
	$apps[$x]['menu'][$y]['uuid'] = "a1b2c3d4-a001-0002-0001-ef1234567890";
	$apps[$x]['menu'][$y]['parent_uuid'] = "594d99c5-6128-9c88-ca35-4b33392cec0f"; //advanced menu
	$apps[$x]['menu'][$y]['category'] = "internal";
	$apps[$x]['menu'][$y]['icon'] = "fa-solid fa-ticket";
	$apps[$x]['menu'][$y]['path'] = "/app/tickets/tickets.php";
	$apps[$x]['menu'][$y]['groups'][] = "superadmin";
	$apps[$x]['menu'][$y]['groups'][] = "admin";
	$apps[$x]['menu'][$y]['groups'][] = "user";

?>
