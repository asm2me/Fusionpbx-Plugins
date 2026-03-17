<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('reseller_domains_add')) {
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
	$reseller_profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
	if (!$reseller_profile && !permission_exists('reseller_all')) {
		echo "access denied";
		exit;
	}

	//admin can specify reseller
	if (permission_exists('reseller_all') && isset($_REQUEST['reseller_uuid']) && is_uuid($_REQUEST['reseller_uuid'])) {
		$reseller_uuid = $_REQUEST['reseller_uuid'];
		$reseller_profile = $reseller_obj->get_profile($reseller_uuid);
	} elseif ($reseller_profile) {
		$reseller_uuid = $reseller_profile['reseller_uuid'];
	}

//process form submission
	if (count($_POST) > 0 && strlen($_POST['persistformvar']) == 0) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-access_denied'], 'negative');
			header('Location: reseller_domains.php');
			exit;
		}

		$domain_name = trim($_POST['domain_name'] ?? '');
		$plan_uuid = $_POST['plan_uuid'] ?? '';
		$num_extensions = (int) ($_POST['num_extensions'] ?? 10);
		$num_gateways = (int) ($_POST['num_gateways'] ?? 1);
		$admin_username = trim($_POST['admin_username'] ?? '');
		$admin_password = $_POST['admin_password'] ?? '';
		$admin_password_confirm = $_POST['admin_password_confirm'] ?? '';

		$errors = [];

		//validate domain name
		if (empty($domain_name)) {
			$errors[] = $text['label-new_domain_name'] . ' is required.';
		}

		//validate passwords match
		if (!empty($admin_username) && $admin_password !== $admin_password_confirm) {
			$errors[] = $text['message-passwords_mismatch'];
		}

		//validate quotas
		$domains_remaining = $reseller_obj->get_remaining_quota($reseller_uuid, 'domains');
		if ($domains_remaining <= 0) {
			$errors[] = $text['message-quota_exceeded'] . ' (domains)';
		}

		$ext_remaining = $reseller_obj->get_remaining_quota($reseller_uuid, 'extensions');
		if ($num_extensions > $ext_remaining) {
			$errors[] = $text['message-quota_exceeded'] . ' (extensions: ' . $ext_remaining . ' remaining)';
		}

		//validate plan limits if a plan was selected
		if (!empty($plan_uuid) && is_uuid($plan_uuid)) {
			$sql = "select * from v_reseller_plans where reseller_plan_uuid = :plan_uuid and reseller_uuid = :reseller_uuid ";
			$parameters['plan_uuid'] = $plan_uuid;
			$parameters['reseller_uuid'] = $reseller_uuid;
			$database = new database;
			$plan = $database->select($sql, $parameters, 'row');
			unset($parameters);

			if (is_array($plan)) {
				if ($num_extensions > (int)$plan['max_extensions'] && (int)$plan['max_extensions'] > 0) {
					$errors[] = $text['message-quota_exceeded'] . ' (plan max extensions: ' . $plan['max_extensions'] . ')';
				}
				if ($num_gateways > (int)$plan['max_gateways'] && (int)$plan['max_gateways'] > 0) {
					$errors[] = $text['message-quota_exceeded'] . ' (plan max gateways: ' . $plan['max_gateways'] . ')';
				}
			}
		}

		if (count($errors) > 0) {
			foreach ($errors as $err) {
				message::add($err, 'negative');
			}
		} else {
			//create the domain
			$domain_data = [
				'domain_name' => $domain_name,
				'plan_uuid' => $plan_uuid,
				'num_extensions' => $num_extensions,
				'num_gateways' => $num_gateways,
				'admin_username' => $admin_username,
				'admin_password' => $admin_password,
			];

			$result = $reseller_obj->create_domain($reseller_uuid, $domain_data);

			if ($result['success']) {
				message::add($text['message-domain_created']);
				header('Location: reseller_domains.php');
				exit;
			} else {
				message::add($result['message'], 'negative');
			}
		}
	}

//get reseller's plans
	$sql = "select * from v_reseller_plans where reseller_uuid = :reseller_uuid and enabled = 'true' order by plan_name asc ";
	$parameters['reseller_uuid'] = $reseller_uuid;
	$database = new database;
	$plans = $database->select($sql, $parameters, 'all');
	unset($parameters);
	if (!is_array($plans)) { $plans = []; }

//get quotas for display
	$domains_remaining = $reseller_obj->get_remaining_quota($reseller_uuid, 'domains');
	$extensions_remaining = $reseller_obj->get_remaining_quota($reseller_uuid, 'extensions');
	$gateways_remaining = $reseller_obj->get_remaining_quota($reseller_uuid, 'gateways');

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-reseller_domain_create'];
	require_once "resources/header.php";

//show the content
	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-reseller_domain_create']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>'arrow-left','link'=>'reseller_domains.php']);
	echo button::create(['type'=>'submit','label'=>$text['button-create_domain'],'icon'=>'check','id'=>'btn_save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<div class='card'>\n";
	echo "	<p>".$text['description-reseller_domain_create']."</p>\n";
	echo "</div>\n";

	//quota information
	echo "<div class='card' style='margin-bottom: 15px; padding: 15px;'>\n";
	echo "	<b>".$text['label-domains_remaining'].":</b> ".(int)$domains_remaining." | ";
	echo "	<b>".$text['label-extensions_remaining'].":</b> ".(int)$extensions_remaining." | ";
	echo "	<b>Gateways Remaining:</b> ".(int)$gateways_remaining."\n";
	echo "</div>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-new_domain_name']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='domain_name' maxlength='255' value='".escape($domain_name ?? '')."' required='required' placeholder='example.com'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-select_plan']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='plan_uuid' id='plan_uuid'>\n";
	echo "			<option value=''>-- Select Plan --</option>\n";
	foreach ($plans as $plan) {
		$selected = (isset($plan_uuid) && $plan_uuid === $plan['reseller_plan_uuid']) ? "selected='selected'" : '';
		echo "			<option value='".escape($plan['reseller_plan_uuid'])."' ".$selected." data-max-ext='".escape($plan['max_extensions'])."' data-max-gw='".escape($plan['max_gateways'])."'>".escape($plan['plan_name'])."</option>\n";
	}
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-num_extensions']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='num_extensions' min='0' max='".(int)$extensions_remaining."' value='".escape($num_extensions ?? '10')."' required='required'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-num_gateways']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='num_gateways' min='0' max='".(int)$gateways_remaining."' value='".escape($num_gateways ?? '1')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr><td colspan='2'><br/><h3>Admin User</h3></td></tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-admin_username']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='admin_username' maxlength='255' value='".escape($admin_username ?? '')."' placeholder='admin'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-admin_password']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='password' name='admin_password' maxlength='255' value=''>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-admin_password_confirm']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='password' name='admin_password_confirm' maxlength='255' value=''>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	echo "<input type='hidden' name='persistformvar' value=''>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>\n";

	//javascript to update limits based on selected plan
	echo "<script>\n";
	echo "document.getElementById('plan_uuid').addEventListener('change', function() {\n";
	echo "	var opt = this.options[this.selectedIndex];\n";
	echo "	var maxExt = opt.getAttribute('data-max-ext');\n";
	echo "	var maxGw = opt.getAttribute('data-max-gw');\n";
	echo "	if (maxExt) { document.querySelector('input[name=num_extensions]').max = maxExt; }\n";
	echo "	if (maxGw) { document.querySelector('input[name=num_gateways]').max = maxGw; }\n";
	echo "});\n";
	echo "</script>\n";

//include the footer
	require_once "resources/footer.php";

?>
