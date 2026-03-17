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

//determine mode: superadmin direct or reseller
	$is_superadmin = permission_exists('reseller_all');
	$reseller_profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
	$reseller_uuid = null;
	$direct_mode = false;

	if ($reseller_profile) {
		//user is a reseller
		$reseller_uuid = $reseller_profile['reseller_uuid'];
	} elseif ($is_superadmin) {
		//superadmin without reseller profile - direct mode
		$direct_mode = true;
		//admin can optionally specify a reseller
		if (isset($_REQUEST['reseller_uuid']) && is_uuid($_REQUEST['reseller_uuid'])) {
			$reseller_uuid = $_REQUEST['reseller_uuid'];
			$reseller_profile = $reseller_obj->get_profile($reseller_uuid);
			$direct_mode = false;
		}
	} else {
		echo "access denied";
		exit;
	}

//process form submission
	if (count($_POST) > 0 && strlen($_POST['persistformvar']) == 0) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-access_denied'] ?? 'Access Denied', 'negative');
			header('Location: reseller_domains.php');
			exit;
		}

		$domain_name = trim($_POST['domain_name'] ?? '');
		$num_extensions = (int) ($_POST['num_extensions'] ?? 10);
		$num_gateways = (int) ($_POST['num_gateways'] ?? 1);
		$num_ivrs = (int) ($_POST['num_ivrs'] ?? 0);
		$num_ring_groups = (int) ($_POST['num_ring_groups'] ?? 0);
		$admin_username = trim($_POST['admin_username'] ?? '');
		$admin_password = $_POST['admin_password'] ?? '';
		$admin_password_confirm = $_POST['admin_password_confirm'] ?? '';
		$source_domain_uuid = $_POST['source_domain_uuid'] ?? '';

		$errors = [];

		//validate domain name
		if (empty($domain_name)) {
			$errors[] = ($text['label-new_domain_name'] ?? 'Domain Name') . ' is required.';
		}

		//validate passwords match
		if (!empty($admin_username) && $admin_password !== $admin_password_confirm) {
			$errors[] = $text['message-passwords_mismatch'] ?? 'Passwords do not match.';
		}

		if ($direct_mode) {
			//superadmin direct creation - no quota checks

			//check if domain name already exists
			$sql = "select count(*) as num from v_domains where domain_name = :domain_name ";
			$parameters['domain_name'] = $domain_name;
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			unset($parameters);
			if (is_array($row) && (int)$row['num'] > 0) {
				$errors[] = 'Domain name already exists.';
			}

		} else {
			//reseller mode - check quotas
			$plan_uuid = $_POST['plan_uuid'] ?? '';

			$domains_remaining = $reseller_obj->get_remaining_quota($reseller_uuid, 'domains');
			if ($domains_remaining <= 0) {
				$errors[] = ($text['message-quota_exceeded'] ?? 'Quota exceeded') . ' (domains)';
			}

			$ext_remaining = $reseller_obj->get_remaining_quota($reseller_uuid, 'extensions');
			if ($num_extensions > $ext_remaining) {
				$errors[] = ($text['message-quota_exceeded'] ?? 'Quota exceeded') . ' (extensions: ' . $ext_remaining . ' remaining)';
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
						$errors[] = ($text['message-quota_exceeded'] ?? 'Quota exceeded') . ' (plan max extensions: ' . $plan['max_extensions'] . ')';
					}
					if ($num_gateways > (int)$plan['max_gateways'] && (int)$plan['max_gateways'] > 0) {
						$errors[] = ($text['message-quota_exceeded'] ?? 'Quota exceeded') . ' (plan max gateways: ' . $plan['max_gateways'] . ')';
					}
				}
			}
		}

		if (count($errors) > 0) {
			foreach ($errors as $err) {
				message::add($err, 'negative');
			}
		} else {
			$domain_data = [
				'domain_name' => $domain_name,
				'num_extensions' => $num_extensions,
				'num_gateways' => $num_gateways,
				'num_ivrs' => $num_ivrs,
				'num_ring_groups' => $num_ring_groups,
				'admin_username' => $admin_username,
				'admin_password' => $admin_password,
				'source_domain_uuid' => $source_domain_uuid,
			];

			if ($direct_mode) {
				//superadmin direct creation
				$result = $reseller_obj->create_domain_direct($domain_data);
			} else {
				//reseller creation
				$domain_data['plan_uuid'] = $plan_uuid ?? '';
				$result = $reseller_obj->create_domain($reseller_uuid, $domain_data);
			}

			//store log in session for display
			if (isset($result['log']) && is_array($result['log'])) {
				$_SESSION['domain_create_log'] = $result['log'];
			}

			if ($result['success']) {
				message::add($text['message-domain_created'] ?? 'Domain created successfully.');
				header('Location: reseller_domain_create.php?result=success');
				exit;
			} else {
				message::add($result['message'], 'negative');
			}
		}
	}

//get available source domains for cloning (superadmin direct mode)
	if ($direct_mode) {
		$sql = "select domain_uuid, domain_name from v_domains where domain_enabled = 'true' order by domain_name asc ";
		$database = new database;
		$source_domains = $database->select($sql, null, 'all');
		if (!is_array($source_domains)) { $source_domains = []; }
	}

//get reseller's plans (reseller mode)
	if (!$direct_mode && $reseller_uuid) {
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
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-reseller_domain_create'] ?? 'Create Domain';
	require_once "resources/header.php";

//show the content
	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".($direct_mode ? ($text['header-create_domain_direct'] ?? 'Create Domain') : ($text['header-reseller_domain_create'] ?? 'Create Domain'))."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'] ?? 'Back','icon'=>'arrow-left','link'=>'reseller_domains.php']);
	echo button::create(['type'=>'submit','label'=>$text['button-create_domain'] ?? 'Create Domain','icon'=>'check','id'=>'btn_save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	//show creation log if available
	if (isset($_SESSION['domain_create_log']) && is_array($_SESSION['domain_create_log'])) {
		$log_entries = $_SESSION['domain_create_log'];
		unset($_SESSION['domain_create_log']);
		$log_type = (isset($_GET['result']) && $_GET['result'] === 'success') ? 'success' : 'warning';
		echo "<div class='card' style='margin-bottom: 15px; padding: 15px; border-left: 4px solid " . ($log_type === 'success' ? '#4CAF50' : '#FF9800') . "; background-color: " . ($log_type === 'success' ? '#f0fff0' : '#fff8f0') . ";'>\n";
		echo "	<b>Domain Creation Log:</b><br/>\n";
		echo "	<div style='font-family: monospace; font-size: 12px; margin-top: 8px; max-height: 300px; overflow-y: auto; padding: 10px; background: #fafafa; border: 1px solid #ddd; border-radius: 4px;'>\n";
		foreach ($log_entries as $entry) {
			$color = '#333';
			if (strpos($entry, 'FAILED') !== false) { $color = '#d32f2f'; }
			elseif (strpos($entry, 'WARNING') !== false) { $color = '#f57c00'; }
			elseif (strpos($entry, 'OK:') !== false) { $color = '#388e3c'; }
			elseif (strpos($entry, 'INFO:') !== false) { $color = '#1976d2'; }
			echo "		<div style='color: " . $color . "; margin-bottom: 4px;'>" . escape($entry) . "</div>\n";
		}
		echo "	</div>\n";
		echo "</div>\n";
	}

	if ($direct_mode) {
		echo "<div class='card'>\n";
		echo "	<p>".($text['description-create_domain_direct'] ?? 'Create a new domain directly as superadmin. No reseller quotas apply. Optionally clone from an existing domain.')."</p>\n";
		echo "</div>\n";
	} else {
		echo "<div class='card'>\n";
		echo "	<p>".($text['description-reseller_domain_create'] ?? 'Create a new domain within your reseller quota.')."</p>\n";
		echo "</div>\n";

		//quota information
		echo "<div class='card' style='margin-bottom: 15px; padding: 15px;'>\n";
		echo "	<b>".($text['label-domains_remaining'] ?? 'Domains Remaining').":</b> ".(int)$domains_remaining." | ";
		echo "	<b>".($text['label-extensions_remaining'] ?? 'Extensions Remaining').":</b> ".(int)$extensions_remaining." | ";
		echo "	<b>".($text['label-gateways_remaining'] ?? 'Gateways Remaining').":</b> ".(int)$gateways_remaining."\n";
		echo "</div>\n";
	}

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".($text['label-new_domain_name'] ?? 'Domain Name')."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='domain_name' maxlength='255' value='".escape($domain_name ?? '')."' required='required' placeholder='example.com'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	if ($direct_mode) {
		//source domain to clone from
		echo "<tr>\n";
		echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".($text['label-source_domain'] ?? 'Clone From Domain')."</td>\n";
		echo "	<td class='vtable' align='left'>\n";
		echo "		<select class='formfld' name='source_domain_uuid' id='source_domain_uuid'>\n";
		echo "			<option value=''>-- None (Empty Domain) --</option>\n";
		foreach ($source_domains as $sd) {
			$selected = (isset($source_domain_uuid) && $source_domain_uuid === $sd['domain_uuid']) ? "selected='selected'" : '';
			echo "			<option value='".escape($sd['domain_uuid'])."' ".$selected.">".escape($sd['domain_name'])."</option>\n";
		}
		echo "		</select>\n";
		echo "		<br><span class='description'>".($text['description-source_domain'] ?? 'Optionally select an existing domain to clone settings from.')."</span>\n";
		echo "	</td>\n";
		echo "</tr>\n";
	} else {
		//reseller plan selection
		echo "<tr>\n";
		echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".($text['label-select_plan'] ?? 'Plan')."</td>\n";
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
	}

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".($text['label-num_extensions'] ?? 'Number of Extensions')."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	if ($direct_mode) {
		echo "		<input class='formfld' type='number' name='num_extensions' min='0' max='9999' value='".escape($num_extensions ?? '10')."' required='required'>\n";
	} else {
		echo "		<input class='formfld' type='number' name='num_extensions' min='0' max='".(int)$extensions_remaining."' value='".escape($num_extensions ?? '10')."' required='required'>\n";
	}
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".($text['label-num_gateways'] ?? 'Number of Gateways')."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	if ($direct_mode) {
		echo "		<input class='formfld' type='number' name='num_gateways' min='0' max='999' value='".escape($num_gateways ?? '1')."'>\n";
	} else {
		echo "		<input class='formfld' type='number' name='num_gateways' min='0' max='".(int)$gateways_remaining."' value='".escape($num_gateways ?? '1')."'>\n";
	}
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".($text['label-num_ivrs'] ?? 'Number of IVRs')."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='num_ivrs' min='0' max='999' value='".escape($num_ivrs ?? '0')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".($text['label-num_ring_groups'] ?? 'Number of Ring Groups')."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='num_ring_groups' min='0' max='999' value='".escape($num_ring_groups ?? '0')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr><td colspan='2'><br/><h3>".($text['label-admin_user'] ?? 'Admin User')."</h3></td></tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".($text['label-admin_username'] ?? 'Username')."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='admin_username' maxlength='255' value='".escape($admin_username ?? '')."' placeholder='admin'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".($text['label-admin_password'] ?? 'Password')."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='password' name='admin_password' maxlength='255' value=''>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".($text['label-admin_password_confirm'] ?? 'Confirm Password')."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='password' name='admin_password_confirm' maxlength='255' value=''>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	echo "<input type='hidden' name='persistformvar' value=''>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>\n";

	if (!$direct_mode) {
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
	}

//include the footer
	require_once "resources/footer.php";

?>
