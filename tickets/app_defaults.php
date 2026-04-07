<?php

/*
	FusionPBX - Support Tickets
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Database schema and default data for the ticketing system.
*/

if ($domains_processed == 1) {

	//default settings
	$y = 0;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0001-0001-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets";
	$array['default_settings'][$y]['default_setting_subcategory'] = "enabled";
	$array['default_settings'][$y]['default_setting_name'] = "boolean";
	$array['default_settings'][$y]['default_setting_value'] = "true";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Enable or disable the support tickets system.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0001-0002-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets";
	$array['default_settings'][$y]['default_setting_subcategory'] = "webphone_report";
	$array['default_settings'][$y]['default_setting_name'] = "boolean";
	$array['default_settings'][$y]['default_setting_value'] = "true";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Allow users to report call issues from web phone history.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0001-0003-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets";
	$array['default_settings'][$y]['default_setting_subcategory'] = "auto_attach_log";
	$array['default_settings'][$y]['default_setting_name'] = "boolean";
	$array['default_settings'][$y]['default_setting_value'] = "true";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Automatically attach web phone activity log when ticket created from call history.";

	//add or update the default settings
	$p = new permissions;
	$p->add("default_setting_add", "temp");
	$p->add("default_setting_edit", "temp");

	$database = new database;
	$database->app_name = "tickets";
	$database->app_uuid = "a1b2c3d4-e5f6-7890-abcd-ef1234567890";
	$database->save($array);
	unset($array);

	$p->delete("default_setting_add", "temp");
	$p->delete("default_setting_edit", "temp");

	//create tickets table
	$sql  = "CREATE TABLE IF NOT EXISTS v_tickets ( ";
	$sql .= "ticket_uuid uuid PRIMARY KEY, ";
	$sql .= "domain_uuid uuid NOT NULL, ";
	$sql .= "user_uuid uuid NOT NULL, ";
	$sql .= "ticket_number varchar(20) NOT NULL, ";
	$sql .= "subject varchar(255) NOT NULL, ";
	$sql .= "description text, ";
	$sql .= "status varchar(20) NOT NULL DEFAULT 'open', ";
	$sql .= "priority varchar(20) NOT NULL DEFAULT 'normal', ";
	$sql .= "source varchar(20) NOT NULL DEFAULT 'panel', ";
	$sql .= "extension varchar(20), ";
	$sql .= "call_uuid uuid, ";
	$sql .= "call_direction varchar(20), ";
	$sql .= "call_number varchar(64), ";
	$sql .= "call_timestamp timestamptz, ";
	$sql .= "call_duration integer, ";
	$sql .= "call_status varchar(20), ";
	$sql .= "call_quality_mos numeric(3,1), ";
	$sql .= "call_quality_rating varchar(20), ";
	$sql .= "call_quality_issues text, ";
	$sql .= "call_hangup_by varchar(20), ";
	$sql .= "call_hangup_cause varchar(64), ";
	$sql .= "assigned_to uuid, ";
	$sql .= "resolved_note text, ";
	$sql .= "insert_date timestamptz DEFAULT now(), ";
	$sql .= "insert_user uuid, ";
	$sql .= "update_date timestamptz, ";
	$sql .= "update_user uuid ";
	$sql .= ") ";
	$database = new database;
	$database->execute($sql);
	unset($sql);

	//create ticket_number unique index
	$sql = "CREATE UNIQUE INDEX IF NOT EXISTS idx_tickets_number ON v_tickets (domain_uuid, ticket_number)";
	$database->execute($sql);
	unset($sql);

	//create ticket replies table
	$sql  = "CREATE TABLE IF NOT EXISTS v_ticket_replies ( ";
	$sql .= "ticket_reply_uuid uuid PRIMARY KEY, ";
	$sql .= "ticket_uuid uuid NOT NULL, ";
	$sql .= "domain_uuid uuid NOT NULL, ";
	$sql .= "user_uuid uuid NOT NULL, ";
	$sql .= "reply_text text NOT NULL, ";
	$sql .= "is_admin boolean NOT NULL DEFAULT false, ";
	$sql .= "insert_date timestamptz DEFAULT now(), ";
	$sql .= "insert_user uuid ";
	$sql .= ") ";
	$database->execute($sql);
	unset($sql);

	//create ticket attachments table
	$sql  = "CREATE TABLE IF NOT EXISTS v_ticket_attachments ( ";
	$sql .= "ticket_attachment_uuid uuid PRIMARY KEY, ";
	$sql .= "ticket_uuid uuid NOT NULL, ";
	$sql .= "domain_uuid uuid NOT NULL, ";
	$sql .= "file_name varchar(255) NOT NULL, ";
	$sql .= "file_type varchar(64) NOT NULL, ";
	$sql .= "file_content text, ";
	$sql .= "attachment_type varchar(30) NOT NULL DEFAULT 'user_upload', ";
	$sql .= "insert_date timestamptz DEFAULT now(), ";
	$sql .= "insert_user uuid ";
	$sql .= ") ";
	$database->execute($sql);
	unset($sql);

	//create ticket status history table (tracks status changes for alerts)
	$sql  = "CREATE TABLE IF NOT EXISTS v_ticket_status_log ( ";
	$sql .= "ticket_status_log_uuid uuid PRIMARY KEY, ";
	$sql .= "ticket_uuid uuid NOT NULL, ";
	$sql .= "domain_uuid uuid NOT NULL, ";
	$sql .= "old_status varchar(20), ";
	$sql .= "new_status varchar(20) NOT NULL, ";
	$sql .= "changed_by uuid, ";
	$sql .= "note text, ";
	$sql .= "insert_date timestamptz DEFAULT now() ";
	$sql .= ") ";
	$database->execute($sql);
	unset($sql);
}

?>
