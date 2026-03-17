<?php

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('reseller_add') && !permission_exists('reseller_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//include the reseller class
	require_once "resources/classes/reseller.php";
	$reseller_obj = new reseller;

//set the action (add or update)
	if (isset($_REQUEST['id']) && is_uuid($_REQUEST['id'])) {
		$action = 'update';
		$reseller_uuid = $_REQUEST['id'];
	} else {
		$action = 'add';
	}

//get http post variables and process the form
	if (count($_POST) > 0 && strlen($_POST['persistformvar']) == 0) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-access_denied'], 'negative');
			header('Location: reseller.php');
			exit;
		}

		//process the http post data
		$reseller_uuid = $_POST['reseller_uuid'] ?? uuid();
		$user_uuid = $_POST['user_uuid'] ?? '';
		$domain_uuid = $_POST['domain_uuid'] ?? '';
		$company_name = $_POST['company_name'] ?? '';
		$contact_name = $_POST['contact_name'] ?? '';
		$contact_email = $_POST['contact_email'] ?? '';
		$contact_phone = $_POST['contact_phone'] ?? '';
		$address = $_POST['address'] ?? '';
		$tax_id = $_POST['tax_id'] ?? '';
		$commission_rate = $_POST['commission_rate'] ?? '10';
		$status = $_POST['status'] ?? 'pending';
		$max_domains = $_POST['max_domains'] ?? '50';
		$max_total_extensions = $_POST['max_total_extensions'] ?? '500';
		$max_total_gateways = $_POST['max_total_gateways'] ?? '50';
		$allowed_features_json = $_POST['allowed_features_json'] ?? '[]';
		$notes = $_POST['notes'] ?? '';
		$enabled = $_POST['enabled'] ?? 'true';

		//branding
		$branding = [
			'logo_url' => $_POST['logo_url'] ?? '',
			'company_name' => $_POST['branding_company_name'] ?? '',
			'support_email' => $_POST['support_email'] ?? '',
			'support_phone' => $_POST['support_phone'] ?? '',
		];
		$branding_json = json_encode($branding);

		//API key generation
		$api_key = $_POST['api_key'] ?? '';
		$api_secret_hash = '';
		if (isset($_POST['generate_api_key']) && $_POST['generate_api_key'] === 'true') {
			$keys = $reseller_obj->generate_api_key();
			$api_key = $keys['api_key'];
			$api_secret_hash = hash('sha256', $keys['api_secret']);
			//flash the secret to the user (shown once)
			$_SESSION['reseller_new_api_secret'] = $keys['api_secret'];
		}

		//build the save array
		$array['reseller_profiles'][0]['reseller_uuid'] = $reseller_uuid;
		$array['reseller_profiles'][0]['user_uuid'] = $user_uuid;
		$array['reseller_profiles'][0]['domain_uuid'] = $domain_uuid;
		$array['reseller_profiles'][0]['company_name'] = $company_name;
		$array['reseller_profiles'][0]['contact_name'] = $contact_name;
		$array['reseller_profiles'][0]['contact_email'] = $contact_email;
		$array['reseller_profiles'][0]['contact_phone'] = $contact_phone;
		$array['reseller_profiles'][0]['address'] = $address;
		$array['reseller_profiles'][0]['tax_id'] = $tax_id;
		$array['reseller_profiles'][0]['commission_rate'] = $commission_rate;
		$array['reseller_profiles'][0]['status'] = $status;
		$array['reseller_profiles'][0]['max_domains'] = $max_domains;
		$array['reseller_profiles'][0]['max_total_extensions'] = $max_total_extensions;
		$array['reseller_profiles'][0]['max_total_gateways'] = $max_total_gateways;
		$array['reseller_profiles'][0]['allowed_features_json'] = $allowed_features_json;
		$array['reseller_profiles'][0]['branding_json'] = $branding_json;
		$array['reseller_profiles'][0]['notes'] = $notes;
		$array['reseller_profiles'][0]['enabled'] = $enabled;

		if (!empty($api_key)) {
			$array['reseller_profiles'][0]['api_key'] = $api_key;
		}
		if (!empty($api_secret_hash)) {
			$array['reseller_profiles'][0]['api_secret'] = $api_secret_hash;
		}

		if ($action === 'add') {
			$array['reseller_profiles'][0]['add_date'] = 'now()';
			$array['reseller_profiles'][0]['add_user'] = $_SESSION['user_uuid'];
		}
		$array['reseller_profiles'][0]['mod_date'] = 'now()';
		$array['reseller_profiles'][0]['mod_user'] = $_SESSION['user_uuid'];

		//grant temp permission
		$p = new permissions;
		$p->add('reseller_add', 'temp');
		$p->add('reseller_edit', 'temp');

		//save to the database
		$database = new database;
		$database->app_name = 'reseller';
		$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
		$database->save($array);
		unset($array);

		$p->delete('reseller_add', 'temp');
		$p->delete('reseller_edit', 'temp');

		//redirect
		message::add($text['message-settings_saved']);
		header('Location: reseller_edit.php?id='.urlencode($reseller_uuid));
		exit;
	}

//pre-populate the form
	if ($action === 'update' && isset($reseller_uuid)) {
		$profile = $reseller_obj->get_profile($reseller_uuid);
		if ($profile) {
			$user_uuid = $profile['user_uuid'];
			$domain_uuid = $profile['domain_uuid'];
			$company_name = $profile['company_name'];
			$contact_name = $profile['contact_name'];
			$contact_email = $profile['contact_email'];
			$contact_phone = $profile['contact_phone'];
			$address = $profile['address'];
			$tax_id = $profile['tax_id'];
			$commission_rate = $profile['commission_rate'];
			$status = $profile['status'];
			$max_domains = $profile['max_domains'];
			$max_total_extensions = $profile['max_total_extensions'];
			$max_total_gateways = $profile['max_total_gateways'];
			$allowed_features_json = $profile['allowed_features_json'];
			$branding = json_decode($profile['branding_json'], true) ?: [];
			$api_key = $profile['api_key'];
			$notes = $profile['notes'];
			$enabled = $profile['enabled'];
		}
	}

//get list of users for the dropdown
	$sql = "select user_uuid, username from v_users order by username asc ";
	$database = new database;
	$users = $database->select($sql, null, 'all');

//get list of domains for the dropdown
	$sql = "select domain_uuid, domain_name from v_domains order by domain_name asc ";
	$database = new database;
	$domains_list = $database->select($sql, null, 'all');

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-reseller_edit'];
	require_once "resources/header.php";

//show the content
	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-reseller_edit']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>'arrow-left','id'=>'btn_back','link'=>'reseller.php?show=all']);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>'check','id'=>'btn_save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<div class='card'>\n";
	echo "	<p>".$text['description-reseller_edit']."</p>\n";
	echo "</div>\n";

	//show API secret flash message if present
	if (isset($_SESSION['reseller_new_api_secret'])) {
		echo "<div class='card' style='background-color: #fff3cd; padding: 15px; margin-bottom: 15px;'>\n";
		echo "	<b>".$text['label-api_secret'].":</b> ".escape($_SESSION['reseller_new_api_secret'])."<br/>\n";
		echo "	<small>".$text['message-api_key_generated']."</small>\n";
		echo "</div>\n";
		unset($_SESSION['reseller_new_api_secret']);
	}

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-company_name']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='company_name' maxlength='255' value='".escape($company_name ?? '')."' required='required'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-user_uuid']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='user_uuid'>\n";
	echo "			<option value=''></option>\n";
	if (is_array($users)) {
		foreach ($users as $u) {
			$selected = (isset($user_uuid) && $u['user_uuid'] === $user_uuid) ? "selected='selected'" : '';
			echo "			<option value='".escape($u['user_uuid'])."' ".$selected.">".escape($u['username'])."</option>\n";
		}
	}
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-domain_uuid']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='domain_uuid'>\n";
	echo "			<option value=''></option>\n";
	if (is_array($domains_list)) {
		foreach ($domains_list as $dl) {
			$selected = (isset($domain_uuid) && $dl['domain_uuid'] === $domain_uuid) ? "selected='selected'" : '';
			echo "			<option value='".escape($dl['domain_uuid'])."' ".$selected.">".escape($dl['domain_name'])."</option>\n";
		}
	}
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-contact_name']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='contact_name' maxlength='255' value='".escape($contact_name ?? '')."' required='required'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-contact_email']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='email' name='contact_email' maxlength='255' value='".escape($contact_email ?? '')."' required='required'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-contact_phone']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='contact_phone' maxlength='32' value='".escape($contact_phone ?? '')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-address']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<textarea class='formfld' name='address' rows='3'>".escape($address ?? '')."</textarea>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-tax_id']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='tax_id' maxlength='64' value='".escape($tax_id ?? '')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-commission_rate']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='commission_rate' min='0' max='100' step='0.01' value='".escape($commission_rate ?? '10')."' required='required'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-status']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='status'>\n";
	$statuses = ['active', 'suspended', 'pending'];
	foreach ($statuses as $s) {
		$selected = (isset($status) && $status === $s) ? "selected='selected'" : '';
		echo "			<option value='".$s."' ".$selected.">".$text['option-'.$s]."</option>\n";
	}
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-max_domains']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='max_domains' min='0' value='".escape($max_domains ?? '50')."' required='required'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-max_total_extensions']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='max_total_extensions' min='0' value='".escape($max_total_extensions ?? '500')."' required='required'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-max_total_gateways']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='max_total_gateways' min='0' value='".escape($max_total_gateways ?? '50')."' required='required'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-allowed_features']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<textarea class='formfld' name='allowed_features_json' rows='3' placeholder='[\"extensions\",\"gateways\",\"ivrs\",\"ring_groups\"]'>".escape($allowed_features_json ?? '[]')."</textarea>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	//branding section
	echo "<tr><td colspan='2'><br/><h3>".$text['label-branding']."</h3></td></tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-logo_url']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='url' name='logo_url' maxlength='512' value='".escape($branding['logo_url'] ?? '')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-branding_company_name']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='branding_company_name' maxlength='255' value='".escape($branding['company_name'] ?? '')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-support_email']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='email' name='support_email' maxlength='255' value='".escape($branding['support_email'] ?? '')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-support_phone']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='support_phone' maxlength='32' value='".escape($branding['support_phone'] ?? '')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	//API section
	echo "<tr><td colspan='2'><br/><h3>API</h3></td></tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-api_key']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='api_key' value='".escape($api_key ?? '')."' readonly='readonly' style='width: 50%; display: inline-block;'>\n";
	echo "		<label><input type='checkbox' name='generate_api_key' value='true'> ".$text['button-generate_api_key']."</label>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-notes']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<textarea class='formfld' name='notes' rows='4'>".escape($notes ?? '')."</textarea>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-enabled']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='enabled'>\n";
	echo "			<option value='true' ".(($enabled ?? 'true') === 'true' ? "selected='selected'" : '').">".$text['option-true']."</option>\n";
	echo "			<option value='false' ".(($enabled ?? 'true') === 'false' ? "selected='selected'" : '').">".$text['option-false']."</option>\n";
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	if ($action === 'update') {
		echo "<input type='hidden' name='reseller_uuid' value='".escape($reseller_uuid)."'>\n";
	} else {
		echo "<input type='hidden' name='reseller_uuid' value='".uuid()."'>\n";
	}
	echo "<input type='hidden' name='persistformvar' value=''>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
