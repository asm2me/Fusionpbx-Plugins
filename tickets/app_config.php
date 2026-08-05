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
	$apps[$x]['permissions'][$y]['menu']['uuid'] = "a1b2c3d4-a001-0001-0001-ef1234567890";
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

	//SMS gateway notification settings (strictly per-domain, configured under Domain > Settings)
	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0001-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "enabled";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "false";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Enable SMS notifications for tickets via a GSM gateway.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0002-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "gateway_type";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "SMS gateway brand: dinstar or goip.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0003-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "gateway_host";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "IP address or hostname of the SMS gateway.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0004-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "gateway_port";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Gateway HTTP port. Leave blank for the brand default (443 for Dinstar, 80 for GoIP).";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0005-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "gateway_username";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Gateway login username.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0006-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "gateway_password";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Gateway login password.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0007-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "gateway_channel";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "0";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "GSM port/channel number on the gateway to send from (0-31 for Dinstar, gateway-specific for GoIP).";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0008-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "verify_ssl";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "false";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Verify the gateway's TLS certificate (Dinstar only). Most units use a self-signed certificate, so this is off by default.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0009-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "notify_support_enabled";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "false";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Text a fixed support number on ticket events.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0010-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "notify_support_number";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Support phone number to notify, e.g. +15551234567.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0011-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "notify_support_events";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "created";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Comma-separated events that text the support number: created, status_changed, updated.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0012-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "notify_customer_enabled";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "false";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Text the customer (the ticket's linked call_number) on ticket events.";
	$y++;

	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0013-0001-ef1234567890";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "notify_customer_events";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "status_changed";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Comma-separated events that text the customer: created, status_changed, updated. Requires the ticket to have a call_number.";
	$y++;

	//menu items – Applications menu entry
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

	//menu items – Advanced menu entry
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
