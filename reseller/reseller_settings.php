<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('reseller_settings_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//include the reseller class
	require_once __DIR__ . "/resources/classes/reseller.php";
	$reseller_obj = new reseller;

//get the reseller profile
	$is_admin = permission_exists('reseller_all');

	if ($is_admin && isset($_REQUEST['reseller_uuid']) && is_uuid($_REQUEST['reseller_uuid'])) {
		$reseller_uuid = $_REQUEST['reseller_uuid'];
		$profile = $reseller_obj->get_profile($reseller_uuid);
	} else {
		$profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
		$reseller_uuid = $profile ? $profile['reseller_uuid'] : '';
	}

	if (!$profile) {
		echo "access denied";
		exit;
	}

//process form submission
	if (count($_POST) > 0 && strlen($_POST['persistformvar']) == 0) {

		$token_check = new token;
		if (!$token_check->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-access_denied'], 'negative');
			header('Location: reseller_settings.php');
			exit;
		}

		//check edit permission
		if (!permission_exists('reseller_profile_edit')) {
			message::add($text['message-access_denied'], 'negative');
			header('Location: reseller_settings.php');
			exit;
		}

		//update contact info
		$contact_name = $_POST['contact_name'] ?? $profile['contact_name'];
		$contact_email = $_POST['contact_email'] ?? $profile['contact_email'];
		$contact_phone = $_POST['contact_phone'] ?? $profile['contact_phone'];
		$address = $_POST['address'] ?? $profile['address'];

		//update branding
		$branding = [
			'logo_url' => $_POST['logo_url'] ?? '',
			'company_name' => $_POST['branding_company_name'] ?? '',
			'support_email' => $_POST['support_email'] ?? '',
			'support_phone' => $_POST['support_phone'] ?? '',
		];
		$branding_json = json_encode($branding);

		$array['v_reseller_profiles'][0]['reseller_uuid'] = $reseller_uuid;
		$array['v_reseller_profiles'][0]['contact_name'] = $contact_name;
		$array['v_reseller_profiles'][0]['contact_email'] = $contact_email;
		$array['v_reseller_profiles'][0]['contact_phone'] = $contact_phone;
		$array['v_reseller_profiles'][0]['address'] = $address;
		$array['v_reseller_profiles'][0]['branding_json'] = $branding_json;
		$array['v_reseller_profiles'][0]['mod_date'] = 'now()';
		$array['v_reseller_profiles'][0]['mod_user'] = $_SESSION['user_uuid'];

		//handle API key generation
		if (isset($_POST['generate_api_key']) && $_POST['generate_api_key'] === 'true') {
			$keys = $reseller_obj->generate_api_key();
			$array['v_reseller_profiles'][0]['api_key'] = $keys['api_key'];
			$array['v_reseller_profiles'][0]['api_secret'] = hash('sha256', $keys['api_secret']);
			$_SESSION['reseller_new_api_secret'] = $keys['api_secret'];
		}

		$p = new permissions;
		$p->add('v_reseller_profiles_edit', 'temp');

		$database = new database;
		$database->app_name = 'reseller';
		$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
		$database->save($array);
		unset($array);

		$p->delete('v_reseller_profiles_edit', 'temp');

		//log the change
		$reseller_obj->log_activity($reseller_uuid, 'settings_changed', [
			'changed_by' => $_SESSION['user_uuid'],
		]);

		message::add($text['message-settings_saved']);
		header('Location: reseller_settings.php');
		exit;
	}

//decode branding
	$branding = json_decode($profile['branding_json'] ?? '{}', true) ?: [];

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-reseller_settings'];
	require_once "resources/header.php";

//show the content
	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-reseller_settings']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>'arrow-left','link'=>'reseller.php']);
	if (permission_exists('reseller_profile_edit')) {
		echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>'check','id'=>'btn_save']);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<div class='card'>\n";
	echo "	<p>".$text['description-reseller_settings']."</p>\n";
	echo "</div>\n";

	//show API secret flash message
	if (isset($_SESSION['reseller_new_api_secret'])) {
		echo "<div class='card' style='background-color: #fff3cd; padding: 15px; margin-bottom: 15px;'>\n";
		echo "	<b>".$text['label-api_secret'].":</b> ".escape($_SESSION['reseller_new_api_secret'])."<br/>\n";
		echo "	<small>".$text['message-api_key_generated']."</small>\n";
		echo "</div>\n";
		unset($_SESSION['reseller_new_api_secret']);
	}

	//account info card (read-only)
	echo "<div class='card' style='margin-bottom: 15px; padding: 15px;'>\n";
	echo "	<h3>".$text['label-company_name']."</h3>\n";
	echo "	<table class='list'>\n";
	echo "		<tr><td><b>".$text['label-company_name']."</b></td><td>".escape($profile['company_name'])."</td></tr>\n";
	echo "		<tr><td><b>".$text['label-status']."</b></td><td>".escape($profile['status'])."</td></tr>\n";
	echo "		<tr><td><b>".$text['label-max_domains']."</b></td><td>".escape($profile['max_domains'])."</td></tr>\n";
	echo "		<tr><td><b>".$text['label-max_total_extensions']."</b></td><td>".escape($profile['max_total_extensions'])."</td></tr>\n";
	echo "		<tr><td><b>".$text['label-max_total_gateways']."</b></td><td>".escape($profile['max_total_gateways'])."</td></tr>\n";
	echo "		<tr><td><b>".$text['label-commission_rate']."</b></td><td>".escape($profile['commission_rate'])."%</td></tr>\n";
	echo "	</table>\n";
	echo "</div>\n";

	//editable contact info
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr><td colspan='2'><h3>Contact Information</h3></td></tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-contact_name']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='contact_name' maxlength='255' value='".escape($profile['contact_name'])."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-contact_email']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='email' name='contact_email' maxlength='255' value='".escape($profile['contact_email'])."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-contact_phone']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='contact_phone' maxlength='32' value='".escape($profile['contact_phone'])."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-address']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<textarea class='formfld' name='address' rows='3'>".escape($profile['address'])."</textarea>\n";
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

	//API credentials section
	echo "<tr><td colspan='2'><br/><h3>API Credentials</h3></td></tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-api_key']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' value='".escape($profile['api_key'] ?? 'Not generated')."' readonly='readonly' style='width: 50%; display: inline-block;'>\n";
	echo "		<label><input type='checkbox' name='generate_api_key' value='true'> ".$text['button-generate_api_key']."</label>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	echo "<input type='hidden' name='persistformvar' value=''>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
