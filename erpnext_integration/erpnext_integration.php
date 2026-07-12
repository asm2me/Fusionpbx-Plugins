<?php

/*
	FusionPBX - ERPNext / Frappe Integration
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Settings page. Reads/writes the erpnext.* domain settings for the current
	domain and provides a "Test Connection" action.
*/

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once dirname(__FILE__) . "/resources/classes/erpnext.php";

//check permissions
	if (!permission_exists('erpnext_integration_view')) {
		echo "access denied";
		exit;
	}

//multi-lingual support
	$language = new text;
	$text = $language->get();

	$domain_uuid = $_SESSION['domain_uuid'];

	//settings we manage (subcategory => type)
	$fields = [
		'enabled'            => 'boolean',
		'url'                => 'text',
		'api_key'            => 'text',
		'api_secret'         => 'text',
		'shared_secret'      => 'text',
		'recording_base_url' => 'text',
		'push_cdr'           => 'boolean',
		'screen_pop'         => 'boolean',
		'verify_tls'         => 'boolean',
	];

//handle save
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
		if (!permission_exists('erpnext_integration_edit')) {
			echo "access denied"; exit;
		}
		//validate the FusionPBX token
		$token = new token;
		if (!$token->validate('/app/erpnext_integration/erpnext_integration.php')) {
			$_SESSION['message'] = "Invalid token.";
			header("Location: erpnext_integration.php"); exit;
		}

		$database = new database;
		foreach ($fields as $name => $type) {
			if ($type === 'boolean') {
				$value = isset($_POST[$name]) && $_POST[$name] === 'true' ? 'true' : 'false';
			} else {
				$value = trim($_POST[$name] ?? '');
			}

			//upsert into v_domain_settings
			$sql = "select domain_setting_uuid from v_domain_settings ";
			$sql .= "where domain_uuid = :domain_uuid and domain_setting_category = 'erpnext' and domain_setting_subcategory = :sub";
			$parameters = ['domain_uuid' => $domain_uuid, 'sub' => $name];
			$existing = $database->select($sql, $parameters, 'column');
			unset($sql, $parameters);

			if ($existing) {
				$sql = "update v_domain_settings set domain_setting_value = :value, domain_setting_enabled = 'true' where domain_setting_uuid = :uuid";
				$parameters = ['value' => $value, 'uuid' => $existing];
			} else {
				$sql = "insert into v_domain_settings (domain_setting_uuid, domain_uuid, domain_setting_category, domain_setting_subcategory, domain_setting_name, domain_setting_value, domain_setting_enabled) ";
				$sql .= "values (:uuid, :domain_uuid, 'erpnext', :sub, :type, :value, 'true')";
				$parameters = ['uuid' => uuid(), 'domain_uuid' => $domain_uuid, 'sub' => $name, 'type' => $type, 'value' => $value];
			}
			$p = new permissions;
			$p->add('domain_setting_add', 'temp');
			$p->add('domain_setting_edit', 'temp');
			$database->execute($sql, $parameters);
			$p->delete('domain_setting_add', 'temp');
			$p->delete('domain_setting_edit', 'temp');
			unset($sql, $parameters);
		}

		$_SESSION['message'] = $text['message-saved'];
		header("Location: erpnext_integration.php"); exit;
	}

//handle test connection (ajax)
	if (($_GET['action'] ?? '') === 'test') {
		header('Content-Type: application/json');
		$erpnext = new erpnext($domain_uuid);
		$user = $erpnext->test_connection();
		if ($user !== false) {
			echo json_encode(['status' => 'ok', 'user' => $user]);
		} else {
			echo json_encode(['status' => 'fail', 'error' => $erpnext->last_error]);
		}
		exit;
	}

//load current values
	$erpnext = new erpnext($domain_uuid);
	$values = [];
	foreach ($fields as $name => $type) {
		$values[$name] = $erpnext->get($name, $type === 'boolean' ? 'false' : '');
	}

//render
	$document['title'] = $text['title-erpnext_integration'];
	require_once dirname(__DIR__, 2) . "/resources/header.php";

	//create a token for the save form
	$token = new token;
	$token_array = $token->create('/app/erpnext_integration/erpnext_integration.php');

	echo "<form method='post' action='erpnext_integration.php'>\n";
	echo "<input type='hidden' name='action' value='save'>\n";
	echo "<input type='hidden' name='".$token_array['name']."' value='".$token_array['hash']."'>\n";

	//this configuration applies only to the current domain (strictly per-domain)
	$current_domain_name = $_SESSION['domain_name'] ?? '';

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-erpnext_integration']."</b>";
	if ($current_domain_name !== '') {
		echo " <span style='font-weight:normal;font-size:0.85em;color:#888;'>&mdash; ".htmlspecialchars($current_domain_name)."</span>";
	}
	echo "</div>\n";
	echo "	<div class='actions'>\n";
	echo "		<button type='button' class='btn btn-default' onclick='erpnext_test();'>".$text['button-test']."</button>\n";
	if (permission_exists('erpnext_integration_edit')) {
		echo "		<button type='submit' class='btn btn-primary'>".$text['button-save']."</button>\n";
	}
	echo "	</div>\n";
	echo "</div>\n";
	echo "<div style='clear:both;'></div>\n";
	echo "<div class='card'>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	function erpnext_bool_row($label, $name, $value) {
		echo "<tr><td class='vncell' valign='top'>".$label."</td><td class='vtable'>";
		echo "<select name='".$name."' class='formfld'>";
		echo "<option value='true'"  .($value === 'true'  ? " selected" : "").">True</option>";
		echo "<option value='false'" .($value !== 'true'  ? " selected" : "").">False</option>";
		echo "</select></td></tr>\n";
	}
	function erpnext_text_row($label, $name, $value, $type = 'text', $hint = '') {
		echo "<tr><td class='vncell' valign='top'>".$label."</td><td class='vtable'>";
		echo "<input type='".$type."' name='".$name."' value='".htmlspecialchars($value)."' class='formfld' style='width:420px;' autocomplete='off'>";
		if ($hint) echo "<br><span class='vexpl'>".$hint."</span>";
		echo "</td></tr>\n";
	}

	erpnext_bool_row($text['label-enabled'], 'enabled', $values['enabled']);
	erpnext_text_row($text['label-url'], 'url', $values['url'], 'text', 'https://erp.example.com');
	erpnext_text_row($text['label-api_key'], 'api_key', $values['api_key']);
	erpnext_text_row($text['label-api_secret'], 'api_secret', $values['api_secret'], 'password');
	erpnext_text_row($text['label-shared_secret'], 'shared_secret', $values['shared_secret'], 'password', 'ERPNext sends this in X-Fusionpbx-Secret for originate / lookup calls.');
	erpnext_text_row($text['label-recording_base_url'], 'recording_base_url', $values['recording_base_url'], 'text', 'e.g. https://pbx.example.com/app/xml_cdr/xml_cdr_download.php?id=');
	erpnext_bool_row($text['label-push_cdr'], 'push_cdr', $values['push_cdr']);
	erpnext_bool_row($text['label-screen_pop'], 'screen_pop', $values['screen_pop']);
	erpnext_bool_row($text['label-verify_tls'], 'verify_tls', $values['verify_tls']);

	echo "</table>\n</div>\n</form>\n";

	echo "<div id='erpnext_test_result' style='margin-top:10px;'></div>\n";
?>
<script>
function erpnext_test() {
	var el = document.getElementById('erpnext_test_result');
	el.innerHTML = 'Testing...';
	fetch('erpnext_integration.php?action=test', {headers: {'Accept': 'application/json'}})
		.then(function(r){ return r.json(); })
		.then(function(d){
			if (d.status === 'ok') {
				el.innerHTML = "<span style='color:green;'>&#10004; Connected as " + (d.user || 'ERPNext') + "</span>";
			} else {
				el.innerHTML = "<span style='color:#c00;'>&#10008; " + (d.error || 'Connection failed') + "</span>";
			}
		})
		.catch(function(e){ el.innerHTML = "<span style='color:#c00;'>Request error: " + e + "</span>"; });
}
</script>
<?php
	require_once dirname(__DIR__, 2) . "/resources/footer.php";
?>
