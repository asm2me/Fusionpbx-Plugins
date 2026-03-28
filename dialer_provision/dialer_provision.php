<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (permission_exists('dialer_provision_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//determine domain scope (superadmin can see all; admin sees their domain only)
	$is_superadmin = (permission_exists('domain_view') || in_array('superadmin', $_SESSION['groups'] ?? []));

//get api_bridge settings from v_default_settings
	$sql = "select default_setting_subcategory, default_setting_value
	        from v_default_settings
	        where default_setting_category = 'api_bridge'
	          and default_setting_enabled = 'true'";
	$database = new database;
	$api_rows = $database->select($sql, null, 'all');
	$api_config = [];
	if (is_array($api_rows)) {
		foreach ($api_rows as $row) {
			$api_config[$row['default_setting_subcategory']] = $row['default_setting_value'];
		}
	}
	$api_key  = $api_config['api_key']  ?? '';
	$api_port = $api_config['api_port'] ?? '3000';
	unset($sql, $api_rows);

//get dialer_provision settings
	$sql = "select default_setting_subcategory, default_setting_value
	        from v_default_settings
	        where default_setting_category = 'dialer_provision'
	          and default_setting_enabled = 'true'";
	$prov_rows = $database->select($sql, null, 'all');
	$prov_config = [];
	if (is_array($prov_rows)) {
		foreach ($prov_rows as $row) {
			$prov_config[$row['default_setting_subcategory']] = $row['default_setting_value'];
		}
	}
	$default_stun     = $prov_config['default_stun']    ?? 'stun:stun.l.google.com:19302';
	$provision_url    = $prov_config['provision_url']   ?? 'https://voipat.com/provision';
	unset($sql, $prov_rows);

//get domains list (superadmin sees all; admin sees their own)
	if ($is_superadmin) {
		$sql = "select domain_uuid, domain_name from v_domains where domain_enabled = 'true' order by domain_name asc";
		$domains = $database->select($sql, null, 'all');
	}
	else {
		$sql = "select domain_uuid, domain_name from v_domains where domain_uuid = :domain_uuid and domain_enabled = 'true'";
		$params = ['domain_uuid' => $_SESSION['domain_uuid']];
		$domains = $database->select($sql, $params, 'all');
		unset($params);
	}
	unset($sql);

//selected domain (POST > session default)
	$selected_domain_uuid = '';
	$selected_domain_name = '';
	if (!empty($_POST['domain_uuid']) && is_uuid($_POST['domain_uuid'])) {
		$selected_domain_uuid = $_POST['domain_uuid'];
	}
	elseif (!empty($_SESSION['domain_uuid'])) {
		$selected_domain_uuid = $_SESSION['domain_uuid'];
	}
	if ($selected_domain_uuid && is_array($domains)) {
		foreach ($domains as $d) {
			if ($d['domain_uuid'] === $selected_domain_uuid) {
				$selected_domain_name = $d['domain_name'];
				break;
			}
		}
	}

//get extensions for the selected domain
	$extensions = [];
	if ($selected_domain_uuid) {
		$sql = "select e.extension_uuid, e.extension, e.number_alias,
		               e.effective_caller_id_name, e.effective_caller_id_number,
		               e.enabled
		        from v_extensions e
		        join v_domains d on e.domain_uuid = d.domain_uuid
		        where d.domain_uuid = :domain_uuid
		          and e.enabled = 'true'
		        order by e.extension asc";
		$params = ['domain_uuid' => $selected_domain_uuid];
		$extensions = $database->select($sql, $params, 'all') ?? [];
		unset($sql, $params);
	}

//selected extension
	$selected_extension = $_POST['extension'] ?? (count($extensions) > 0 ? $extensions[0]['extension'] : '');

//default wss from domain
	$default_wss = $selected_domain_name ? "wss://{$selected_domain_name}:7443" : '';

//form field values (persist on POST)
	$f_wss      = $_POST['wss_url']       ?? $default_wss;
	$f_stun     = $_POST['stun']          ?? $default_stun;
	$f_turn     = $_POST['turn']          ?? '';
	$f_turn_u   = $_POST['turn_username'] ?? '';
	$f_turn_p   = $_POST['turn_password'] ?? '';
	$f_codec    = $_POST['codec']         ?? 'PCMU (G.711 µ-law)';
	$f_expires  = $_POST['expires_hours'] ?? '48';

//handle form submission: generate provisioning link
	$generated_url   = '';
	$generated_token = '';
	$generated_ext   = '';
	$generated_disp  = '';
	$generated_exp   = '';
	$gen_error       = '';

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'generate') {

		//validate the token
			$token_obj = new token;
			if (!$token_obj->validate($_SERVER['PHP_SELF'])) {
				message::add('Invalid security token.', 'negative');
			}
		//validate permission
		elseif (!permission_exists('dialer_provision_generate')) {
			$gen_error = 'You do not have permission to generate provisioning links.';
		}
		//validate required fields
		elseif (empty($selected_extension) || empty($selected_domain_name)) {
			$gen_error = 'Please select a domain and extension.';
		}
		else {
			//build request body
				$request_body = [
					'extension'    => $selected_extension,
					'domain'       => $selected_domain_name,
					'wss_url'      => $f_wss      ?: null,
					'stun'         => $f_stun     ?: null,
					'codec'        => $f_codec    ?: null,
					'expires_hours'=> (int)$f_expires,
				];
				if (!empty($f_turn))   $request_body['turn']          = $f_turn;
				if (!empty($f_turn_u)) $request_body['turn_username']  = $f_turn_u;
				if (!empty($f_turn_p)) $request_body['turn_password']  = $f_turn_p;

			//call local API
				$api_url = "http://127.0.0.1:{$api_port}/api/provision/generate";
				$ch = curl_init($api_url);
				curl_setopt_array($ch, [
					CURLOPT_POST           => true,
					CURLOPT_POSTFIELDS     => json_encode($request_body),
					CURLOPT_HTTPHEADER     => [
						'Content-Type: application/json',
						'X-API-Key: ' . $api_key,
					],
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT        => 10,
					CURLOPT_CONNECTTIMEOUT => 5,
				]);
				$raw  = curl_exec($ch);
				$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$curl_err = curl_error($ch);
				curl_close($ch);

			//parse response
				if ($curl_err) {
					$gen_error = "Could not reach the API server: {$curl_err}";
				}
				elseif ($code !== 200) {
					$resp_obj  = json_decode($raw, true);
					$gen_error = $resp_obj['detail'] ?? "API returned HTTP {$code}.";
				}
				else {
					$resp_obj        = json_decode($raw, true);
					$generated_url   = $provision_url . '?t=' . ($resp_obj['token'] ?? '');
					$generated_token = $resp_obj['token']        ?? '';
					$generated_ext   = $resp_obj['extension']    ?? $selected_extension;
					$generated_disp  = $resp_obj['display_name'] ?? '';
					$generated_exp   = $resp_obj['expires_at']   ?? '';
				}
		}

		if ($gen_error) {
			message::add($gen_error, 'negative');
		}
	}

//create CSRF token for the form
	$object = new token;
	$token  = $object->create($_SERVER['PHP_SELF']);

//page title
	$document['title'] = 'Dialer Provision';
	require_once "resources/header.php";

//action bar
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b><i class='fas fa-link'></i>&nbsp; VOIP@ Dialer &mdash; Provisioning Link Generator</b></div>\n";
	echo "	<div class='actions'></div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

//show generated link result at the top
	if ($generated_url) {
		echo "<div style='margin-bottom:24px; padding:20px 24px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;'>\n";
		echo "	<div style='display:flex; align-items:center; gap:10px; margin-bottom:12px;'>\n";
		echo "		<i class='fas fa-check-circle' style='color:#16a34a; font-size:1.25rem;'></i>\n";
		echo "		<strong style='font-size:1rem; color:#15803d;'>Provisioning link generated for ".escape($generated_disp ?: $generated_ext)." (ext ".escape($generated_ext).")</strong>\n";
		echo "	</div>\n";
		echo "	<div style='display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px;'>\n";
		echo "		<input type='text' id='prov_url' value='".escape($generated_url)."' readonly\n";
		echo "		       style='flex:1; min-width:260px; padding:10px 14px; border:1px solid #d1d5db; border-radius:6px; font-size:.875rem; font-family:monospace; background:#fff;'>\n";
		echo "		<button onclick=\"copyProvLink()\" style='padding:10px 18px; background:#6c3fc5; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer;'>\n";
		echo "			<i class='fas fa-copy'></i> Copy\n";
		echo "		</button>\n";
		echo "	</div>\n";
		if ($generated_exp) {
			echo "	<p style='font-size:.8125rem; color:#6b7280; margin:0;'><i class='fas fa-clock'></i> Expires: ".escape($generated_exp)."</p>\n";
		}
		echo "	<div id='prov_qr' style='margin-top:16px;'></div>\n";
		echo "</div>\n";
	}

//form
	echo "<form method='post' name='frm' id='frm'>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
	echo "<input type='hidden' name='action' value='generate'>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	//── Domain selector (superadmin only) ──────────────────────────────────────
	if ($is_superadmin && is_array($domains) && count($domains) > 1) {
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' nowrap='nowrap'>Domain</td>\n";
		echo "<td class='vtable'>\n";
		echo "	<select class='formfld' name='domain_uuid' onchange='this.form.extension.value=\"\"; this.form.submit();'>\n";
		foreach ($domains as $d) {
			$sel = ($d['domain_uuid'] === $selected_domain_uuid) ? "selected='selected'" : '';
			echo "		<option value='".escape($d['domain_uuid'])."' {$sel}>".escape($d['domain_name'])."</option>\n";
		}
		echo "	</select>\n";
		echo "</td>\n";
		echo "</tr>\n";
	}
	else {
		echo "<input type='hidden' name='domain_uuid' value='".escape($selected_domain_uuid)."'>\n";
	}

	//── Extension ──────────────────────────────────────────────────────────────
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' nowrap='nowrap'>Extension</td>\n";
	echo "<td class='vtable'>\n";
	if (is_array($extensions) && count($extensions) > 0) {
		echo "	<select class='formfld' name='extension'>\n";
		foreach ($extensions as $ext) {
			$label  = escape($ext['extension']);
			if (!empty($ext['effective_caller_id_name'])) {
				$label .= ' &mdash; ' . escape($ext['effective_caller_id_name']);
			}
			$sel = ($ext['extension'] === $selected_extension) ? "selected='selected'" : '';
			echo "		<option value='".escape($ext['extension'])."' {$sel}>{$label}</option>\n";
		}
		echo "	</select>\n";
		echo "	<br><span style='font-size:.8125rem; color:var(--color-fg-muted, #6b7280);'>Select the extension to configure on the device.</span>\n";
	}
	else {
		echo "	<span style='color:#ef4444;'>No enabled extensions found for this domain.</span>\n";
		echo "	<input type='hidden' name='extension' value=''>\n";
	}
	echo "</td>\n";
	echo "</tr>\n";

	//── Section: Connection ────────────────────────────────────────────────────
	echo "<tr><td colspan='2' style='padding:16px 0 4px;'><hr style='border-color:#e5e7eb;'><strong>Connection Settings</strong></td></tr>\n";

	//WSS URL
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' nowrap='nowrap'>WSS URL</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<input class='formfld' type='text' name='wss_url' value='".escape($f_wss)."' placeholder='wss://sip.yourdomain.com:7443' style='width:420px;'>\n";
	echo "	<br><span style='font-size:.8125rem; color:var(--color-fg-muted, #6b7280);'>WebSocket Secure signaling URL. Auto-filled from domain name.</span>\n";
	echo "</td>\n";
	echo "</tr>\n";

	//STUN
	echo "<tr>\n";
	echo "<td class='vncell' valign='top' nowrap='nowrap'>STUN Server</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<input class='formfld' type='text' name='stun' value='".escape($f_stun)."' placeholder='stun:stun.l.google.com:19302' style='width:420px;'>\n";
	echo "	<br><span style='font-size:.8125rem; color:var(--color-fg-muted, #6b7280);'>STUN server for NAT traversal.</span>\n";
	echo "</td>\n";
	echo "</tr>\n";

	//TURN server (optional)
	echo "<tr>\n";
	echo "<td class='vncell' valign='top' nowrap='nowrap'>TURN Server <span style='font-weight:400; color:#9ca3af;'>(optional)</span></td>\n";
	echo "<td class='vtable'>\n";
	echo "	<input class='formfld' type='text' name='turn' value='".escape($f_turn)."' placeholder='turn:turn.yourdomain.com:3478' style='width:420px;'>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' nowrap='nowrap'>TURN Username <span style='font-weight:400; color:#9ca3af;'>(optional)</span></td>\n";
	echo "<td class='vtable'>\n";
	echo "	<input class='formfld' type='text' name='turn_username' value='".escape($f_turn_u)."' style='width:280px;'>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' nowrap='nowrap'>TURN Password <span style='font-weight:400; color:#9ca3af;'>(optional)</span></td>\n";
	echo "<td class='vtable'>\n";
	echo "	<input class='formfld' type='password' name='turn_password' value='".escape($f_turn_p)."' style='width:280px;'>\n";
	echo "</td>\n";
	echo "</tr>\n";

	//── Section: Audio ─────────────────────────────────────────────────────────
	echo "<tr><td colspan='2' style='padding:16px 0 4px;'><hr style='border-color:#e5e7eb;'><strong>Audio Settings</strong></td></tr>\n";

	//Codec
	$codecs = [
		'PCMU (G.711 µ-law)' => 'PCMU — G.711 µ-law (recommended for PSTN)',
		'PCMA (G.711 a-law)' => 'PCMA — G.711 A-law',
		'Opus'               => 'Opus (best for internet calls)',
		'G.722'              => 'G.722 (HD voice)',
	];
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' nowrap='nowrap'>Preferred Codec</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<select class='formfld' name='codec'>\n";
	foreach ($codecs as $val => $label) {
		$sel = ($f_codec === $val) ? "selected='selected'" : '';
		echo "		<option value='".escape($val)."' {$sel}>".escape($label)."</option>\n";
	}
	echo "	</select>\n";
	echo "	<br><span style='font-size:.8125rem; color:var(--color-fg-muted, #6b7280);'>Audio codec for SIP calls.</span>\n";
	echo "</td>\n";
	echo "</tr>\n";

	//── Section: Link options ──────────────────────────────────────────────────
	echo "<tr><td colspan='2' style='padding:16px 0 4px;'><hr style='border-color:#e5e7eb;'><strong>Link Options</strong></td></tr>\n";

	//Expiry
	$expiry_options = [
		'24'  => '24 hours',
		'48'  => '48 hours (default)',
		'72'  => '72 hours',
		'168' => '7 days',
	];
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' nowrap='nowrap'>Link Expiry</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<select class='formfld' name='expires_hours'>\n";
	foreach ($expiry_options as $val => $label) {
		$sel = ($f_expires == $val) ? "selected='selected'" : '';
		echo "		<option value='".escape($val)."' {$sel}>".escape($label)."</option>\n";
	}
	echo "	</select>\n";
	echo "	<br><span style='font-size:.8125rem; color:var(--color-fg-muted, #6b7280);'>Link expires after this period. Share with the user before it expires.</span>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	echo "<br>\n";

	//submit button
	if (permission_exists('dialer_provision_generate')) {
		echo button::create([
			'type'  => 'submit',
			'label' => 'Generate Provisioning Link',
			'icon'  => 'link',
			'id'    => 'btn_generate',
			'style' => 'margin-top:8px;',
		]);
	}

	echo "\n</form>\n";

//javascript
	echo "<script src='https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'></script>\n";
	echo "<script>\n";

	//generate QR code if a link was produced
	if ($generated_url) {
		echo "new QRCode(document.getElementById('prov_qr'), {\n";
		echo "    text: '".addslashes($generated_url)."',\n";
		echo "    width: 180, height: 180,\n";
		echo "    colorDark: '#1a1a2e', colorLight: '#ffffff'\n";
		echo "});\n";
	}

	//copy to clipboard
	echo "function copyProvLink() {\n";
	echo "    var el = document.getElementById('prov_url');\n";
	echo "    el.select();\n";
	echo "    el.setSelectionRange(0, 99999);\n";
	echo "    if (navigator.clipboard) {\n";
	echo "        navigator.clipboard.writeText(el.value).then(function() {\n";
	echo "            var btn = event.target.closest('button');\n";
	echo "            btn.innerHTML = \"<i class='fas fa-check'></i> Copied!\";\n";
	echo "            setTimeout(function() { btn.innerHTML = \"<i class='fas fa-copy'></i> Copy\"; }, 2000);\n";
	echo "        });\n";
	echo "    } else {\n";
	echo "        document.execCommand('copy');\n";
	echo "    }\n";
	echo "}\n";
	echo "</script>\n";

//include the footer
	require_once "resources/footer.php";

?>
