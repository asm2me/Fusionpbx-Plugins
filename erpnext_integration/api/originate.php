<?php

/*
	FusionPBX - ERPNext / Frappe Integration
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Click-to-dial endpoint. ERPNext (or the companion Frappe app) calls this to
	originate an outbound call: it rings the agent's extension first, then dials
	the destination number.

	Auth: X-Fusionpbx-Secret header must match the erpnext.shared_secret setting.

	POST /app/erpnext_integration/api/originate.php
	Body (JSON or form):
		domain_uuid   optional - resolved from domain_name or Host if omitted
		domain_name   optional - e.g. pbx.example.com
		extension     required  - agent extension to ring first (e.g. 1001)
		destination   required  - number to dial (e.g. +15551234567 or 5551234567)
		caller_id_name    optional
		caller_id_number  optional
*/

//includes
	require_once dirname(__DIR__, 3) . "/resources/require.php";
	require_once dirname(__DIR__) . "/resources/classes/erpnext.php";

	header('Content-Type: application/json');

	function fail($code, $msg) {
		http_response_code($code);
		echo json_encode(['status' => 'error', 'error' => $msg]);
		exit;
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		fail(405, 'method_not_allowed');
	}

//parse input
	$input = json_decode(file_get_contents('php://input'), true);
	if (empty($input)) $input = $_POST;

	$database = new database;

//resolve domain
	$domain_uuid = $input['domain_uuid'] ?? '';
	$domain_name = $input['domain_name'] ?? ($_SERVER['HTTP_HOST'] ?? '');
	if (!is_uuid($domain_uuid) && $domain_name !== '') {
		$sql = "select domain_uuid from v_domains where domain_name = :name and domain_enabled = 'true'";
		$domain_uuid = $database->select($sql, ['name' => $domain_name], 'column');
		unset($sql);
	}
	if (!is_uuid($domain_uuid)) {
		fail(400, 'domain_not_resolved');
	}

//authenticate via shared secret
	$erpnext = new erpnext($domain_uuid);
	$expected = $erpnext->get('shared_secret', '');
	$provided = $_SERVER['HTTP_X_FUSIONPBX_SECRET'] ?? ($input['shared_secret'] ?? '');
	if ($expected === '' || !hash_equals($expected, (string)$provided)) {
		fail(403, 'invalid_secret');
	}
	if (!$erpnext->is_enabled()) {
		fail(400, 'integration_disabled');
	}

//validate params
	$extension   = preg_replace('/[^\w\-\.\+]/', '', $input['extension'] ?? '');
	$destination = preg_replace('/[^\d\+\*\#]/', '', $input['destination'] ?? '');
	if ($extension === '' || $destination === '') {
		fail(400, 'extension_and_destination_required');
	}

//resolve the domain name (needed as FreeSWITCH sofia domain) if we only had the uuid
	if ($domain_name === '' || is_uuid($domain_name)) {
		$sql = "select domain_name from v_domains where domain_uuid = :uuid";
		$domain_name = $database->select($sql, ['uuid' => $domain_uuid], 'column');
		unset($sql);
	}

	$caller_id_name   = $input['caller_id_name']   ?? 'Click to Dial';
	$caller_id_number = $input['caller_id_number'] ?? $extension;

//connect to FreeSWITCH event socket (uses FusionPBX core event_socket class + config)
	if (!class_exists('event_socket')) {
		$es_class = dirname(__DIR__, 3) . "/resources/classes/event_socket.php";
		if (file_exists($es_class)) require_once $es_class;
	}
	if (!class_exists('event_socket')) {
		fail(500, 'event_socket_unavailable');
	}

	$esl = new event_socket;
	if (!$esl->connect()) {
		fail(502, 'freeswitch_connect_failed');
	}

	$origination_uuid = uuid();

	//channel variables for leg A (the agent phone)
	$vars = [
		"origination_uuid={$origination_uuid}",
		"domain_uuid={$domain_uuid}",
		"domain_name={$domain_name}",
		"origination_caller_id_name='" . str_replace("'", "", $caller_id_name) . "'",
		"origination_caller_id_number={$caller_id_number}",
		"ignore_early_media=true",
		"call_direction=outbound",
		"origination_privacy=name+number",
		"erpnext_click_to_dial=true",
	];
	$var_string = '{' . implode(',', $vars) . '}';

	//ring the agent extension, then bridge to the destination via the outbound context
	$dial_agent = "user/{$extension}@{$domain_name}";
	$cmd  = "originate {$var_string}{$dial_agent} ";
	$cmd .= "&transfer('" . $destination . " XML " . $domain_name . "')";

	$response = $esl->request('api', $cmd);

	$ok = ($response !== false && stripos((string)$response, '+OK') !== false);

	echo json_encode([
		'status'           => $ok ? 'success' : 'error',
		'origination_uuid' => $origination_uuid,
		'extension'        => $extension,
		'destination'      => $destination,
		'response'         => trim((string)$response),
	]);
?>
