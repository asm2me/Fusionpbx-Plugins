<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_subscriptions_add') && !permission_exists('billing_subscriptions_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//action add or update
	if (is_uuid($_REQUEST['id'])) {
		$subscription_uuid = $_REQUEST['id'];
		$action = 'update';
	}
	else {
		$action = 'add';
	}

//get http post variables and save
	if (count($_POST) > 0 && is_uuid($_POST['domain_uuid'])) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative');
			header('Location: billing_subscriptions.php');
			exit;
		}

		//set variables
		$domain_uuid = $_POST['domain_uuid'];
		$plan_uuid = $_POST['plan_uuid'];
		$reseller_uuid = $_POST['reseller_uuid'];
		$status = $_POST['status'];
		$start_date = $_POST['start_date'];
		$end_date = $_POST['end_date'];
		$next_billing_date = $_POST['next_billing_date'];
		$auto_renew = $_POST['auto_renew'];
		$trial_ends_at = $_POST['trial_ends_at'];

		//build array
		$array['v_billing_subscriptions'][0]['domain_uuid'] = $domain_uuid;
		$array['v_billing_subscriptions'][0]['plan_uuid'] = $plan_uuid;
		$array['v_billing_subscriptions'][0]['reseller_uuid'] = !empty($reseller_uuid) ? $reseller_uuid : null;
		$array['v_billing_subscriptions'][0]['status'] = $status;
		$array['v_billing_subscriptions'][0]['start_date'] = $start_date;
		$array['v_billing_subscriptions'][0]['end_date'] = $end_date;
		$array['v_billing_subscriptions'][0]['next_billing_date'] = $next_billing_date;
		$array['v_billing_subscriptions'][0]['auto_renew'] = $auto_renew;
		$array['v_billing_subscriptions'][0]['trial_ends_at'] = !empty($trial_ends_at) ? $trial_ends_at : null;
		$array['v_billing_subscriptions'][0]['mod_date'] = date('Y-m-d H:i:s');
		$array['v_billing_subscriptions'][0]['mod_user'] = $_SESSION['user_uuid'];

		if ($action == 'add') {
			$subscription_uuid = uuid();
			$array['v_billing_subscriptions'][0]['subscription_uuid'] = $subscription_uuid;
			$array['v_billing_subscriptions'][0]['add_date'] = date('Y-m-d H:i:s');
			$array['v_billing_subscriptions'][0]['add_user'] = $_SESSION['user_uuid'];
		}
		else {
			$array['v_billing_subscriptions'][0]['subscription_uuid'] = $subscription_uuid;
		}

		//handle domain activation/suspension based on status change
		require_once __DIR__ . "/resources/classes/billing.php";
		$billing = new billing;
		if ($status == 'suspended') {
			$billing->suspend_domain($domain_uuid);
		}
		elseif ($status == 'active') {
			$billing->activate_domain($domain_uuid);
		}

		//save
		$p = new permissions;
		$p->add('v_billing_subscriptions_'.($action == 'add' ? 'add' : 'edit'), 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array);
		unset($array);
		$p->delete('v_billing_subscription_'.($action == 'add' ? 'add' : 'edit'), 'temp');

		message::add($text['message-saved']);
		header('Location: billing_subscriptions.php');
		exit;
	}

//pre-populate the form
	if ($action == 'update' && is_uuid($subscription_uuid)) {
		$sql = "select * from v_billing_subscriptions where subscription_uuid = :subscription_uuid ";
		$parameters['subscription_uuid'] = $subscription_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row)) {
			$domain_uuid = $row['domain_uuid'];
			$plan_uuid = $row['plan_uuid'];
			$reseller_uuid = $row['reseller_uuid'];
			$status = $row['status'];
			$start_date = $row['start_date'];
			$end_date = $row['end_date'];
			$next_billing_date = $row['next_billing_date'];
			$auto_renew = $row['auto_renew'];
			$trial_ends_at = $row['trial_ends_at'];
		}
		unset($sql, $parameters, $row);
	}

//set defaults
	if (strlen($status) == 0) { $status = 'active'; }
	if (strlen($auto_renew) == 0) { $auto_renew = 'true'; }
	if (strlen($start_date) == 0) { $start_date = date('Y-m-d'); }

//get domains list
	$sql = "select domain_uuid, domain_name from v_domains order by domain_name asc ";
	$database = new database;
	$domains = $database->select($sql, null, 'all');
	unset($sql);

//get plans list
	$sql = "select plan_uuid, plan_name, price, currency, billing_cycle from v_billing_plans where enabled = 'true' order by plan_name asc ";
	$database = new database;
	$plans = $database->select($sql, null, 'all');
	unset($sql);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-billing_subscription_edit'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_subscription_edit']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>'arrow-left','link'=>'billing_subscriptions.php']);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>'check','id'=>'btn_save','form'=>'frm_edit']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_subscription_edit']."\n";
	echo "<br /><br />\n";

	echo "<form id='frm_edit' method='post'>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top'>".$text['label-domain']."</td>\n";
	echo "<td width='70%' class='vtable'>\n";
	echo "	<select class='formfld' name='domain_uuid' required='required'>\n";
	echo "		<option value=''>-- Select Domain --</option>\n";
	if (is_array($domains)) {
		foreach ($domains as $d) {
			echo "		<option value='".escape($d['domain_uuid'])."' ".($domain_uuid == $d['domain_uuid'] ? "selected='selected'" : "").">".escape($d['domain_name'])."</option>\n";
		}
	}
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-plan']."</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<select class='formfld' name='plan_uuid' required='required' id='plan_select'>\n";
	echo "		<option value=''>-- Select Plan --</option>\n";
	if (is_array($plans)) {
		foreach ($plans as $p_row) {
			echo "		<option value='".escape($p_row['plan_uuid'])."' ".($plan_uuid == $p_row['plan_uuid'] ? "selected='selected'" : "").">".escape($p_row['plan_name'])." (".number_format($p_row['price'], 2)." ".$p_row['currency']." / ".$p_row['billing_cycle'].")</option>\n";
		}
	}
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-reseller']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='text' name='reseller_uuid' value='".escape($reseller_uuid)."' placeholder='Optional reseller UUID'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-status']."</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<select class='formfld' name='status'>\n";
	echo "		<option value='active' ".($status == 'active' ? "selected='selected'" : "").">".$text['option-active']."</option>\n";
	echo "		<option value='suspended' ".($status == 'suspended' ? "selected='selected'" : "").">".$text['option-suspended']."</option>\n";
	echo "		<option value='cancelled' ".($status == 'cancelled' ? "selected='selected'" : "").">".$text['option-cancelled']."</option>\n";
	echo "		<option value='expired' ".($status == 'expired' ? "selected='selected'" : "").">".$text['option-expired']."</option>\n";
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-start_date']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='date' name='start_date' value='".escape(substr($start_date, 0, 10))."' required='required'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-end_date']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='date' name='end_date' value='".escape(substr($end_date, 0, 10))."' required='required'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-next_billing_date']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='date' name='next_billing_date' value='".escape(substr($next_billing_date, 0, 10))."'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-auto_renew']."</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<select class='formfld' name='auto_renew'>\n";
	echo "		<option value='true' ".($auto_renew == 'true' ? "selected='selected'" : "").">".$text['label-true']."</option>\n";
	echo "		<option value='false' ".($auto_renew == 'false' ? "selected='selected'" : "").">".$text['label-false']."</option>\n";
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-trial_ends_at']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='date' name='trial_ends_at' value='".escape(substr($trial_ends_at, 0, 10))."'></td>\n";
	echo "</tr>\n";

	echo "</table>\n";
	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
