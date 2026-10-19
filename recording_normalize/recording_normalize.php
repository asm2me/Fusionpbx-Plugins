<?php

/*
	FusionPBX - Recording Normalize
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1
*/

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";
	require_once __DIR__ . "/resources/classes/recording_normalize.php";

//check permissions
	if (!permission_exists('recording_normalize_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

	$domain_uuid = $_SESSION['domain_uuid'];
	$domain_name = $_SESSION['domain_name'];
	$normalizer = new recording_normalize;
	$settings = $normalizer->settings;
	$tools = $normalizer->tools();

//stream a recording (current or saved original) for the preview player, or its waveform peaks
	$request_uuid = $_GET['play'] ?? $_GET['peaks'] ?? '';
	if (!empty($request_uuid) && is_uuid($request_uuid)) {
		$row = $normalizer->get_recording($domain_uuid, $request_uuid);
		if (!$row) {
			http_response_code(404);
			exit;
		}
		$file = basename($row['recording_filename']);
		$dir = $normalizer->domain_dir($domain_name);
		$path = (($_GET['v'] ?? '') === 'original') ? $dir.'/'.recording_normalize::BACKUP_DIR.'/'.$file : $dir.'/'.$file;
		if (!is_file($path)) {
			http_response_code(404);
			exit;
		}
		if (isset($_GET['peaks'])) {
			session_write_close();
			header('Content-Type: application/json');
			header('Cache-Control: no-store');
			echo json_encode($normalizer->peaks($path));
			exit;
		}
		$types = ['wav' => 'audio/wav', 'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg'];
		header('Content-Type: '.($types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
		header('Content-Length: '.filesize($path));
		header('Cache-Control: no-store');
		readfile($path);
		exit;
	}

//form values (POST > default settings)
	$f_mode = (($_POST['mode'] ?? $settings['mode']) === 'loudness') ? 'loudness' : 'peak';
	$f_target_lufs = is_numeric($_POST['target_lufs'] ?? null) ? $_POST['target_lufs'] : $settings['target_lufs'];
	$f_peak_percent = is_numeric($_POST['peak_percent'] ?? null) ? $_POST['peak_percent'] : $settings['peak_percent'];
	$f_ivr_only = isset($_POST['action']) ? !empty($_POST['ivr_only']) : (($_GET['ivr_only'] ?? '1') === '1');

//normalize or restore the selected recordings
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['normalize', 'restore'], true)) {
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'] ?? 'Invalid security token.', 'negative');
		}
		elseif (!permission_exists('recording_normalize_edit')) {
			message::add('You do not have permission to change recordings.', 'negative');
		}
		else {
			$selected = array_filter((array)($_POST['recordings'] ?? []), 'is_uuid');
			if (empty($selected)) {
				message::add('Select at least one recording.', 'negative');
			}
			else {
				set_time_limit(600);
				$options = [
					'mode' => $f_mode,
					'target_lufs' => min(-5, max(-40, (float)$f_target_lufs)),
					'peak_percent' => min(100, max(10, (float)$f_peak_percent)),
				];
				$ok = [];
				$failed = [];
				foreach ($selected as $recording_uuid) {
					$result = ($_POST['action'] === 'normalize')
						? $normalizer->normalize($domain_uuid, $domain_name, $recording_uuid, $options)
						: $normalizer->restore($domain_uuid, $domain_name, $recording_uuid);
					if ($result['ok']) {
						$ok[] = escape($result['message']);
					}
					else {
						$failed[] = escape($result['message']);
					}
				}
				if ($ok) message::add(implode('<br>', $ok), 'positive', 6000);
				if ($failed) message::add(implode('<br>', $failed), 'negative', 10000);
			}
		}
	}

//get the recordings
	$recordings = $normalizer->get_recordings($domain_uuid, $domain_name);
	$ivr_count = count(array_filter($recordings, function ($r) { return !empty($r['ivr_menus']); }));
	if ($f_ivr_only) {
		$recordings = array_values(array_filter($recordings, function ($r) { return !empty($r['ivr_menus']); }));
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-recording_normalize'];
	require_once "resources/header.php";

//action bar
	echo "<form method='post' name='frm' id='frm'>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
	echo "<input type='hidden' name='action' id='action' value=''>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".escape($text['title-recording_normalize'])."</b><div class='count'>".count($recordings)."</div></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('recording_normalize_edit')) {
		echo button::create(['type' => 'button', 'label' => 'Restore Original', 'icon' => 'undo', 'id' => 'btn_restore', 'onclick' => "rn_submit('restore');"]);
		echo button::create(['type' => 'button', 'label' => 'Normalize', 'icon' => 'volume-up', 'id' => 'btn_normalize', 'style' => 'margin-left: 15px;', 'onclick' => "rn_submit('normalize');"]);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo escape($text['description-recording_normalize'])."\n";
	echo "<br /><br />\n";

//tool availability
	if (!$tools['ffmpeg'] && !$tools['sox']) {
		echo "<div style='padding:12px 16px; margin-bottom:16px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px;'>";
		echo "<b>Neither ffmpeg nor sox is installed on this server.</b> Install one to normalize recordings: <code>apt install ffmpeg</code></div>\n";
	}
	elseif (!$tools['ffmpeg']) {
		echo "<div style='padding:12px 16px; margin-bottom:16px; background:#fffbeb; border:1px solid #fde68a; border-radius:6px;'>";
		echo "ffmpeg is not installed, so only <b>peak</b> normalization (sox) is available. Install ffmpeg for loudness normalization: <code>apt install ffmpeg</code></div>\n";
		$f_mode = 'peak';
	}

//options
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td width='20%' class='vncell' valign='top' nowrap='nowrap'>Method</td>\n";
	echo "<td width='80%' class='vtable'>\n";
	echo "	<select class='formfld' name='mode' id='mode' onchange='rn_mode();'>\n";
	echo "		<option value='peak' ".($f_mode === 'peak' ? "selected='selected'" : '').">Peak &mdash; loudest point set to a percent of full scale</option>\n";
	echo "		<option value='loudness' ".($f_mode === 'loudness' ? "selected='selected'" : '')." ".($tools['ffmpeg'] ? '' : 'disabled')
		.">Loudness (EBU R128) &mdash; prompts sound equally loud</option>\n";
	echo "	</select>\n";
	echo "	<br />Loudness evens out prompts that sound different even at the same peak.\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "<tr id='row_lufs'>\n";
	echo "<td class='vncell' valign='top' nowrap='nowrap'>Target Loudness</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<input class='formfld' type='number' step='0.5' min='-40' max='-5' name='target_lufs' value='".escape($f_target_lufs)."' style='width:90px;'> LUFS\n";
	echo "	<br />-18 suits telephone prompts. Use -16 for louder, -20 for quieter.\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "<tr id='row_peak'>\n";
	echo "<td class='vncell' valign='top' nowrap='nowrap'>Target Peak</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<input class='formfld' type='number' step='1' min='10' max='100' name='peak_percent' id='peak_percent' value='".escape($f_peak_percent)."' style='width:90px;' oninput='rn_canvases.forEach(rn_draw);'> %\n";
	echo "	<br />The loudest point of each file is set to this level. 90% leaves a little headroom.\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td class='vncell' valign='top' nowrap='nowrap'>Show</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<label><input type='checkbox' name='ivr_only' value='1' ".($f_ivr_only ? "checked='checked'" : '')." onchange=\"document.getElementById('action').value=''; this.form.submit();\"> Only recordings used by IVR menus ($ivr_count)</label>\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "<br />\n";

//recordings list
	$status_labels = [
		'original' => "<span style='color:#6b7280;'>Original</span>",
		'normalized' => "<span style='color:#16a34a;'><i class='fas fa-check'></i> Normalized</span>",
		'changed' => "<span style='color:#d97706;' title='The file was replaced after it was normalized'>Replaced since</span>",
		'missing' => "<span style='color:#dc2626;'>File missing</span>",
	];

	echo "<div class='card'>\n";
	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "	<th class='checkbox'><input type='checkbox' id='checkbox_all' onclick=\"document.querySelectorAll('.rn_cb').forEach(function(c){ c.checked = document.getElementById('checkbox_all').checked; });\"></th>\n";
	echo "	<th>Name</th>\n";
	echo "	<th>File</th>\n";
	echo "	<th>IVR Menus</th>\n";
	echo "	<th>Status</th>\n";
	echo "	<th class='hide-sm-dn'>Level</th>\n";
	echo "	<th>Waveform</th>\n";
	echo "	<th>Listen</th>\n";
	echo "</tr>\n";
	if (empty($recordings)) {
		echo "<tr class='list-row'><td colspan='8' style='text-align:center; padding:20px;'>".($f_ivr_only ? 'No recordings are used by an IVR menu.' : 'No recordings found.')."</td></tr>\n";
	}
	foreach ($recordings as $row) {
		$state = $row['state'];
		$uuid = escape($row['recording_uuid']);
		$level = '';
		if ($state['status'] === 'normalized') {
			$level = escape(trim(($state['before'] ? $state['before'].' → ' : '').$state['target']));
		}
		echo "<tr class='list-row'>\n";
		echo "	<td class='checkbox'>".($row['exists'] ? "<input type='checkbox' class='rn_cb' name='recordings[]' value='$uuid'>" : '')."</td>\n";
		echo "	<td>".escape($row['recording_name'])."</td>\n";
		echo "	<td>".escape($row['recording_filename'])."</td>\n";
		echo "	<td>".escape(implode(', ', $row['ivr_menus']))."</td>\n";
		echo "	<td>".($status_labels[$state['status']] ?? '')."</td>\n";
		echo "	<td class='hide-sm-dn'>$level</td>\n";
		echo "	<td style='width:240px; max-width:40vw;'>";
		if ($row['exists']) {
			echo "<canvas class='rn_wave' data-uuid='$uuid' data-original='".($state['status'] === 'normalized' ? '1' : '0')."' height='40' "
				."style='display:block; width:100%; height:40px; cursor:pointer;' title='Click to play' onclick=\"rn_play('$uuid', 'current', this);\"></canvas>";
		}
		echo "</td>\n";
		echo "	<td style='white-space:nowrap;'>";
		if ($row['exists']) {
			echo "<button type='button' class='btn btn-default btn-xs' onclick=\"rn_play('$uuid', 'current', this);\" title='Play current'><i class='fas fa-play'></i></button>";
			if ($state['status'] === 'normalized') {
				echo " <button type='button' class='btn btn-default btn-xs' onclick=\"rn_play('$uuid', 'original', this);\" title='Play original'><i class='fas fa-play'></i> Original</button>";
			}
		}
		echo "</td>\n";
		echo "</tr>\n";
	}
	echo "</table>\n";
	echo "</div>\n";
	echo "<br />\n";
	echo "</form>\n";

//javascript
	echo "<audio id='rn_audio'></audio>\n";
	echo "<script>\n";
	echo "function rn_mode() {\n";
	echo "	var peak = document.getElementById('mode').value === 'peak';\n";
	echo "	document.getElementById('row_lufs').style.display = peak ? 'none' : '';\n";
	echo "	document.getElementById('row_peak').style.display = peak ? '' : 'none';\n";
	echo "	if (typeof rn_canvases !== 'undefined') rn_canvases.forEach(rn_draw);\n";
	echo "}\n";
	echo "rn_mode();\n";
	echo "function rn_submit(action) {\n";
	echo "	if (!document.querySelector('.rn_cb:checked')) { alert('Select at least one recording.'); return; }\n";
	echo "	var msg = action === 'normalize' ? 'Normalize the selected recordings? Originals are kept and can be restored.' : 'Restore the original of the selected recordings?';\n";
	echo "	if (!confirm(msg)) return;\n";
	echo "	document.getElementById('action').value = action;\n";
	echo "	document.getElementById('frm').submit();\n";
	echo "}\n";
	echo "//waveforms: drawn on a shared full-scale amplitude so rows can be compared;\n";
	echo "//the saved original (grey) is drawn behind a normalized file\n";
	echo "var rn_waves = {};\n";
	echo "function rn_color(name, fallback) {\n";
	echo "	var v = getComputedStyle(document.body).getPropertyValue(name).trim();\n";
	echo "	return v || fallback;\n";
	echo "}\n";
	echo "function rn_draw(canvas) {\n";
	echo "	var w = rn_waves[canvas.dataset.uuid];\n";
	echo "	if (!w || !w.current) return;\n";
	echo "	var ratio = window.devicePixelRatio || 1;\n";
	echo "	var width = canvas.clientWidth, height = canvas.clientHeight;\n";
	echo "	canvas.width = width * ratio; canvas.height = height * ratio;\n";
	echo "	var ctx = canvas.getContext('2d');\n";
	echo "	ctx.scale(ratio, ratio);\n";
	echo "	ctx.clearRect(0, 0, width, height);\n";
	echo "	var mid = height / 2;\n";
	echo "	ctx.fillStyle = 'rgba(128,128,128,0.15)';\n";
	echo "	ctx.fillRect(0, mid - 0.5, width, 1);\n";
	echo "	function bars(peaks, color, progress) {\n";
	echo "		var n = peaks.length, bar = width / n;\n";
	echo "		for (var i = 0; i < n; i++) {\n";
	echo "			var h = Math.max(1, peaks[i] * (height - 2));\n";
	echo "			ctx.fillStyle = (progress !== undefined && i / n < progress) ? '#16a34a' : color;\n";
	echo "			ctx.fillRect(i * bar, mid - h / 2, Math.max(1, bar - 0.5), h);\n";
	echo "		}\n";
	echo "	}\n";
	echo "	if (w.original) bars(w.original.peaks, 'rgba(128,128,128,0.35)');\n";
	echo "	var target = document.getElementById('mode').value === 'peak' ? parseFloat(document.getElementById('peak_percent').value) / 100 : 0;\n";
	echo "	if (target > 0 && target <= 1) {\n";
	echo "		ctx.fillStyle = 'rgba(220,38,38,0.35)';\n";
	echo "		for (var x = 0; x < width; x += 6) { ctx.fillRect(x, mid - target * (height - 2) / 2, 3, 1); ctx.fillRect(x, mid + target * (height - 2) / 2 - 1, 3, 1); }\n";
	echo "	}\n";
	echo "	bars(w.current.peaks, rn_color('--rn-wave', '#3b82f6'), w.progress);\n";
	echo "}\n";
	echo "function rn_fetch(url) {\n";
	echo "	return fetch(url, {credentials: 'same-origin'}).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });\n";
	echo "}\n";
	echo "function rn_load(canvas) {\n";
	echo "	var uuid = canvas.dataset.uuid;\n";
	echo "	var jobs = [rn_fetch('?peaks=' + uuid + '&v=current')];\n";
	echo "	if (canvas.dataset.original === '1') jobs.push(rn_fetch('?peaks=' + uuid + '&v=original'));\n";
	echo "	Promise.all(jobs).then(function (r) {\n";
	echo "		rn_waves[uuid] = {current: r[0], original: r[1] || null};\n";
	echo "		rn_draw(canvas);\n";
	echo "	});\n";
	echo "}\n";
	echo "var rn_canvases = document.querySelectorAll('.rn_wave');\n";
	echo "if ('IntersectionObserver' in window) {\n";
	echo "	var rn_observer = new IntersectionObserver(function (entries) {\n";
	echo "		entries.forEach(function (e) {\n";
	echo "			if (e.isIntersecting) { rn_observer.unobserve(e.target); rn_load(e.target); }\n";
	echo "		});\n";
	echo "	}, {rootMargin: '200px'});\n";
	echo "	rn_canvases.forEach(function (c) { rn_observer.observe(c); });\n";
	echo "}\n";
	echo "else {\n";
	echo "	rn_canvases.forEach(rn_load);\n";
	echo "}\n";
	echo "window.addEventListener('resize', function () { rn_canvases.forEach(rn_draw); });\n";
	echo "//player: one audio element; the playing row's waveform shows progress\n";
	echo "var rn_playing = null;\n";
	echo "var rn_audio = document.getElementById('rn_audio');\n";
	echo "function rn_canvas(uuid) { return document.querySelector(\".rn_wave[data-uuid='\" + uuid + \"']\"); }\n";
	echo "function rn_stop_progress() {\n";
	echo "	if (!rn_playing) return;\n";
	echo "	var w = rn_waves[rn_playing.uuid];\n";
	echo "	if (w) { delete w.progress; var c = rn_canvas(rn_playing.uuid); if (c) rn_draw(c); }\n";
	echo "}\n";
	echo "function rn_play(uuid, version, btn) {\n";
	echo "	if (rn_playing && rn_playing.uuid === uuid && rn_playing.version === version && !rn_audio.paused) {\n";
	echo "		rn_audio.pause(); rn_stop_progress(); rn_playing = null; return;\n";
	echo "	}\n";
	echo "	rn_stop_progress();\n";
	echo "	rn_playing = {uuid: uuid, version: version};\n";
	echo "	rn_audio.src = '?play=' + uuid + '&v=' + version + '&t=' + Date.now();\n";
	echo "	rn_audio.play();\n";
	echo "}\n";
	echo "rn_audio.addEventListener('timeupdate', function () {\n";
	echo "	if (!rn_playing || !rn_audio.duration) return;\n";
	echo "	var w = rn_waves[rn_playing.uuid];\n";
	echo "	if (!w) return;\n";
	echo "	w.progress = rn_audio.currentTime / rn_audio.duration;\n";
	echo "	var c = rn_canvas(rn_playing.uuid);\n";
	echo "	if (c) rn_draw(c);\n";
	echo "});\n";
	echo "rn_audio.addEventListener('ended', function () { rn_stop_progress(); rn_playing = null; });\n";
	echo "</script>\n";

//include the footer
	require_once "resources/footer.php";

?>
