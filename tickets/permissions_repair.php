<?php

/*
	FusionPBX - Support Tickets Permissions Repair
	Repairs default Support Tickets permissions for superadmin, admin, and user groups.
*/

require_once dirname(__DIR__, 2) . "/resources/require.php";
require_once "resources/check_auth.php";

if (!permission_exists('group_permission_edit') && !if_group("superadmin")) {
	echo "access denied";
	exit;
}

$database = new database;

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

//look up each group's uuid once, not once per permission
$group_uuids = [];
if ($group_permission_has_group_uuid && $group_permission_has_permission_uuid) {
	$sql = "select group_name, group_uuid from v_groups where group_name in ('superadmin', 'admin', 'user')";
	$rows = $database->select($sql, null, 'all') ?: [];
	foreach ($rows as $row) {
		$group_uuids[$row['group_name']] = $row['group_uuid'];
	}
}

$repairs = 0;

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
		$repairs++;
	}

	if ($group_permission_has_group_uuid && $group_permission_has_permission_uuid) {
		$superadmin_group_uuid = $group_uuids['superadmin'] ?? null;

		$superadmin_permission_uuid = $permission_uuid;
		if ($superadmin_group_uuid) {
			$sql = "select permission_uuid from v_group_permissions where group_uuid = :group_uuid and permission_uuid = :permission_uuid";
			$parameters = [
				'group_uuid' => $superadmin_group_uuid,
				'permission_uuid' => $permission_uuid
			];
			$superadmin_permission_uuid = $database->select($sql, $parameters, 'column') ?: $permission_uuid;
		}

		foreach ($group_names as $group_name) {
			$group_uuid = $group_uuids[$group_name] ?? null;

			if (!$group_uuid) {
				continue;
			}

			$sql = "select group_permission_uuid from v_group_permissions where group_uuid = :group_uuid and permission_uuid = :permission_uuid";
			$parameters = [
				'group_uuid' => $group_uuid,
				'permission_uuid' => $superadmin_permission_uuid
			];
			$group_permission_uuid = $database->select($sql, $parameters, 'column');

			if (!$group_permission_uuid) {
				$sql = "insert into v_group_permissions (group_permission_uuid, group_uuid, permission_uuid) values (:group_permission_uuid, :group_uuid, :permission_uuid)";
				$parameters = [
					'group_permission_uuid' => uuid(),
					'group_uuid' => $group_uuid,
					'permission_uuid' => $superadmin_permission_uuid
				];
				$database->execute($sql, $parameters);
				$repairs++;
			}
		}
	}
	elseif ($group_permission_has_group_name && $group_permission_has_permission_name) {
		foreach ($group_names as $group_name) {
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
				$repairs++;
			}
		}
	}
}

$_SESSION['message'] = "Support Tickets permissions repair completed. Changes applied: ".$repairs;
header("Location: /core/groups/groups.php");
exit;

?>
