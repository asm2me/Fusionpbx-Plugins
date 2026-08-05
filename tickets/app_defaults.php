<?php

/*
	FusionPBX - Support Tickets
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Database schema and default data for the ticketing system.
*/

if ($domains_processed == 1) {

	//remove stale menu items (wrong UUIDs from previous installs, including the invalid 'menu' UUID)
	$database = new database;
	$sql = "DELETE FROM v_menu_items
	        WHERE menu_item_link = '/app/tickets/tickets.php'
	          AND menu_item_uuid != 'a1b2c3d4-a001-0001-0001-ef1234567890'";
	$database->execute($sql);
	$sql = null;

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
	$y++;

	//SMS gateway notification settings (strictly per-domain, configured under Domain > Settings)
	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0001-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "enabled";
	$array['default_settings'][$y]['default_setting_name'] = "boolean";
	$array['default_settings'][$y]['default_setting_value'] = "false";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Enable SMS notifications for tickets via a GSM gateway.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0002-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "gateway_type";
	$array['default_settings'][$y]['default_setting_name'] = "text";
	$array['default_settings'][$y]['default_setting_value'] = "";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "SMS gateway brand: dinstar or goip.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0003-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "gateway_host";
	$array['default_settings'][$y]['default_setting_name'] = "text";
	$array['default_settings'][$y]['default_setting_value'] = "";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "IP address or hostname of the SMS gateway.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0004-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "gateway_port";
	$array['default_settings'][$y]['default_setting_name'] = "text";
	$array['default_settings'][$y]['default_setting_value'] = "";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Gateway HTTP port. Leave blank for the brand default (443 for Dinstar, 80 for GoIP).";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0005-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "gateway_username";
	$array['default_settings'][$y]['default_setting_name'] = "text";
	$array['default_settings'][$y]['default_setting_value'] = "";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Gateway login username.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0006-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "gateway_password";
	$array['default_settings'][$y]['default_setting_name'] = "text";
	$array['default_settings'][$y]['default_setting_value'] = "";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Gateway login password.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0007-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "gateway_channel";
	$array['default_settings'][$y]['default_setting_name'] = "text";
	$array['default_settings'][$y]['default_setting_value'] = "0";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "GSM port/channel number on the gateway to send from (0-31 for Dinstar, gateway-specific for GoIP).";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0008-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "verify_ssl";
	$array['default_settings'][$y]['default_setting_name'] = "boolean";
	$array['default_settings'][$y]['default_setting_value'] = "false";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Verify the gateway's TLS certificate (Dinstar only). Most units use a self-signed certificate, so this is off by default.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0009-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "notify_support_enabled";
	$array['default_settings'][$y]['default_setting_name'] = "boolean";
	$array['default_settings'][$y]['default_setting_value'] = "false";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Text a fixed support number on ticket events.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0010-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "notify_support_number";
	$array['default_settings'][$y]['default_setting_name'] = "text";
	$array['default_settings'][$y]['default_setting_value'] = "";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Support phone number to notify, e.g. +15551234567.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0011-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "notify_support_events";
	$array['default_settings'][$y]['default_setting_name'] = "text";
	$array['default_settings'][$y]['default_setting_value'] = "created";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Comma-separated events that text the support number: created, status_changed, updated.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0012-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "notify_customer_enabled";
	$array['default_settings'][$y]['default_setting_name'] = "boolean";
	$array['default_settings'][$y]['default_setting_value'] = "false";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Text the customer (the ticket's linked call_number) on ticket events.";
	$y++;

	$array['default_settings'][$y]['default_setting_uuid'] = "a1b2c3d4-0002-0013-0001-ef1234567890";
	$array['default_settings'][$y]['default_setting_category'] = "tickets_sms";
	$array['default_settings'][$y]['default_setting_subcategory'] = "notify_customer_events";
	$array['default_settings'][$y]['default_setting_name'] = "text";
	$array['default_settings'][$y]['default_setting_value'] = "status_changed";
	$array['default_settings'][$y]['default_setting_enabled'] = "true";
	$array['default_settings'][$y]['default_setting_description'] = "Comma-separated events that text the customer: created, status_changed, updated. Requires the ticket to have a call_number.";

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

	//repair default permissions and group assignments for existing installs
	$ticket_permissions = [
		'ticket_view' => ['superadmin', 'admin', 'user'],
		'ticket_add' => ['superadmin', 'admin', 'user'],
		'ticket_edit' => ['superadmin', 'admin'],
		'ticket_delete' => ['superadmin', 'admin'],
		'ticket_reply' => ['superadmin', 'admin', 'user'],
		'ticket_manage' => ['superadmin', 'admin'],
		'ticket_api' => ['superadmin', 'admin', 'user'],
	];

	//detect schema via pg_catalog directly; information_schema.columns is far slower on a database with many tables
	$sql = "SELECT a.attname AS column_name FROM pg_attribute a JOIN pg_class c ON c.oid = a.attrelid WHERE c.relname = 'v_group_permissions' AND a.attnum > 0 AND NOT a.attisdropped";
	$group_permission_columns = $database->select($sql, null, 'all') ?: [];
	$sql = null;

	$group_permission_has_permission_uuid = false;
	$group_permission_has_permission_name = false;
	$group_permission_has_group_uuid = false;
	$group_permission_has_group_name = false;
	foreach ($group_permission_columns as $column) {
		$column_name = $column['column_name'] ?? '';
		if ($column_name === 'permission_uuid') $group_permission_has_permission_uuid = true;
		if ($column_name === 'permission_name') $group_permission_has_permission_name = true;
		if ($column_name === 'group_uuid') $group_permission_has_group_uuid = true;
		if ($column_name === 'group_name') $group_permission_has_group_name = true;
	}
	unset($group_permission_columns, $column_name);

	//look up each group's uuid once, not once per permission
	$group_uuids = [];
	if ($group_permission_has_group_uuid && $group_permission_has_permission_uuid) {
		$sql = "select group_name, group_uuid from v_groups where group_name in ('superadmin', 'admin', 'user')";
		$rows = $database->select($sql, null, 'all') ?: [];
		foreach ($rows as $row) {
			$group_uuids[$row['group_name']] = $row['group_uuid'];
		}
		unset($sql, $rows, $row);
	}

	foreach ($ticket_permissions as $permission_name => $group_names) {
		$sql = "select permission_uuid from v_permissions where permission_name = :permission_name";
		$parameters = ['permission_name' => $permission_name];
		$permission_uuid = $database->select($sql, $parameters, 'column');

		if (!$permission_uuid) {
			$permission_uuid = uuid();
			$sql = "insert into v_permissions (permission_uuid, permission_name) values (:permission_uuid, :permission_name)";
			$parameters = [
				'permission_uuid' => $permission_uuid,
				'permission_name' => $permission_name
			];
			$database->execute($sql, $parameters);
		}
		$sql = null;
		unset($parameters);

		foreach ($group_names as $group_name) {
			$group_uuid = $group_uuids[$group_name] ?? null;

			if ($group_permission_has_group_uuid && $group_permission_has_permission_uuid) {
				if (!$group_uuid) {
					continue;
				}

				$sql = "select group_permission_uuid from v_group_permissions where group_uuid = :group_uuid and permission_uuid = :permission_uuid";
				$parameters = [
					'group_uuid' => $group_uuid,
					'permission_uuid' => $permission_uuid
				];
				$group_permission_uuid = $database->select($sql, $parameters, 'column');

				if (!$group_permission_uuid) {
					$sql = "insert into v_group_permissions (group_permission_uuid, group_uuid, permission_uuid) values (:group_permission_uuid, :group_uuid, :permission_uuid)";
					$parameters = [
						'group_permission_uuid' => uuid(),
						'group_uuid' => $group_uuid,
						'permission_uuid' => $permission_uuid
					];
					$database->execute($sql, $parameters);
				}
				$sql = null;
				unset($parameters, $group_permission_uuid);
			}
			elseif ($group_permission_has_group_name && $group_permission_has_permission_name) {
				$sql = "select group_permission_uuid from v_group_permissions where group_name = :group_name and permission_name = :permission_name";
				$parameters = [
					'group_name' => $group_name,
					'permission_name' => $permission_name
				];
				$group_permission_uuid = $database->select($sql, $parameters, 'column');

				if (!$group_permission_uuid) {
					$sql = "insert into v_group_permissions (group_permission_uuid, group_name, permission_name) values (:group_permission_uuid, :group_name, :permission_name)";
					$parameters = [
						'group_permission_uuid' => uuid(),
						'group_name' => $group_name,
						'permission_name' => $permission_name
					];
					$database->execute($sql, $parameters);
				}
				$sql = null;
				unset($parameters, $group_permission_uuid);
			}
		}

		unset($permission_uuid);
	}

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
	$sql .= "contact_name varchar(255), ";
	$sql .= "contact_phone varchar(64), ";
	$sql .= "contact_email varchar(255), ";
	$sql .= "assigned_to uuid, ";
	$sql .= "resolved_note text, ";
	$sql .= "insert_date timestamptz DEFAULT now(), ";
	$sql .= "insert_user uuid, ";
	$sql .= "update_date timestamptz, ";
	$sql .= "update_user uuid ";
	$sql .= ") ";
	$database = new database;
	$database->execute($sql);
	$sql = null;

	//add contact columns for installs that already had v_tickets before this update
	$database->execute("ALTER TABLE v_tickets ADD COLUMN IF NOT EXISTS contact_name varchar(255)");
	$database->execute("ALTER TABLE v_tickets ADD COLUMN IF NOT EXISTS contact_phone varchar(64)");
	$database->execute("ALTER TABLE v_tickets ADD COLUMN IF NOT EXISTS contact_email varchar(255)");

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

	//create ticket API keys table (domain-locked credentials for external integrations)
	$sql  = "CREATE TABLE IF NOT EXISTS v_ticket_api_keys ( ";
	$sql .= "ticket_api_key_uuid uuid PRIMARY KEY, ";
	$sql .= "domain_uuid uuid NOT NULL, ";
	$sql .= "user_uuid uuid NOT NULL, ";
	$sql .= "api_key varchar(64) NOT NULL, ";
	$sql .= "api_secret varchar(128) NOT NULL, ";
	$sql .= "label varchar(128), ";
	$sql .= "enabled boolean NOT NULL DEFAULT true, ";
	$sql .= "insert_date timestamptz DEFAULT now(), ";
	$sql .= "insert_user uuid, ";
	$sql .= "last_used_date timestamptz ";
	$sql .= ") ";
	$database->execute($sql);
	unset($sql);

	$sql = "CREATE UNIQUE INDEX IF NOT EXISTS idx_ticket_api_keys_key ON v_ticket_api_keys (api_key)";
	$database->execute($sql);
	unset($sql);
}

?>
