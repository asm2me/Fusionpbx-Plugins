<?php

/*
	FusionPBX - ERPNext / Frappe Integration
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Inbound screen-pop notifier.

	Invoked by the dialplan on an inbound call (via mod_dptools 'system' or a
	lua/luarun action). Tells ERPNext to raise a screen-pop of the matching
	contact for the target agent.

	Usage (from dialplan / CLI):
		php erpnext_screen_pop.php <domain_uuid> <caller_number> <agent_extension> [call_uuid]

	Example dialplan action (Advanced > Dialplan, on the inbound route, before bridge):
		<action application="system"
		        data="php /var/www/fusionpbx/app/erpnext_integration/resources/cron/erpnext_screen_pop.php ${domain_uuid} ${caller_id_number} ${destination_number} ${uuid}"/>
*/

	set_include_path('/var/www/fusionpbx');
	require_once '/var/www/fusionpbx/resources/require.php';
	require_once '/var/www/fusionpbx/app/erpnext_integration/resources/classes/erpnext.php';

	if (php_sapi_name() !== 'cli') { echo "CLI only\n"; exit(1); }

	$domain_uuid = $argv[1] ?? '';
	$caller      = $argv[2] ?? '';
	$agent       = $argv[3] ?? '';
	$call_uuid   = $argv[4] ?? '';

	if (!is_uuid($domain_uuid) || $caller === '') {
		fwrite(STDERR, "usage: erpnext_screen_pop.php <domain_uuid> <caller_number> <agent_extension> [call_uuid]\n");
		exit(1);
	}

	$erpnext = new erpnext($domain_uuid);
	if (!$erpnext->is_enabled() || $erpnext->get('screen_pop', 'true') !== 'true') {
		exit(0); //silently no-op when disabled
	}

	$ok = $erpnext->notify_incoming_call([
		'from'        => $caller,
		'agent'       => $agent,
		'call_uuid'   => $call_uuid,
		'domain_uuid' => $domain_uuid,
	]);

	//non-blocking: never hold the call. Log failures only.
	if (!$ok) {
		fwrite(STDERR, "erpnext_screen_pop: " . $erpnext->last_error . "\n");
	}
	exit(0);
?>
