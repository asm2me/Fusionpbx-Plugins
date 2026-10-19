<?php

/**
 * Recording Normalize Cron Job
 *
 * Normalizes recordings that are new or were re-uploaded since they were last
 * normalized. Does nothing unless recording_normalize -> auto_normalize is true
 * in Default Settings. With auto_ivr_only true (the default) only recordings
 * used by an IVR menu are touched.
 *
 * Run as the web server user so file ownership is kept:
 * Crontab (every 10 minutes): 0,10,20,30,40,50 * * * * sudo -u www-data php /var/www/fusionpbx/app/recording_normalize/recording_normalize_cron.php
 */

//set the include path
	$conf = glob("{/usr/local/etc/fusionpbx,/etc/fusionpbx}/config.conf", GLOB_BRACE);
	set_include_path(parse_ini_file($conf[0])['document.path']);

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once __DIR__ . "/resources/classes/recording_normalize.php";

	set_time_limit(0);

	function recording_normalize_log($message) {
		echo "[".date('Y-m-d H:i:s')."] ".$message."\n";
	}

	$normalizer = new recording_normalize;
	if ($normalizer->settings['auto_normalize'] !== 'true') {
		recording_normalize_log("auto_normalize is off, nothing to do");
		exit;
	}
	$ivr_only = ($normalizer->settings['auto_ivr_only'] ?? 'true') === 'true';

	$database = new database;
	$domains = $database->select("select domain_uuid, domain_name from v_domains where domain_enabled = 'true'", null, 'all');
	foreach ((array)$domains as $domain) {
		foreach ($normalizer->get_recordings($domain['domain_uuid'], $domain['domain_name']) as $row) {
			if (!in_array($row['state']['status'], ['original', 'changed'], true)) continue;
			if ($ivr_only && empty($row['ivr_menus'])) continue;
			$result = $normalizer->normalize($domain['domain_uuid'], $domain['domain_name'], $row['recording_uuid']);
			recording_normalize_log($domain['domain_name'].': '.($result['ok'] ? '' : 'FAILED ').$result['message']);
		}
	}

?>
