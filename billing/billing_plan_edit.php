<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_plans_add') && !permission_exists('billing_plans_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//action add or update
	if (is_uuid($_REQUEST['id'])) {
		$plan_uuid = $_REQUEST['id'];
		$action = 'update';
	}
	else {
		$action = 'add';
	}

//get http post variables and save to the database
	if (count($_POST) > 0 && strlen($_POST['plan_name']) > 0) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative');
			header('Location: billing_plans.php');
			exit;
		}

		//set the variables
		$plan_name = $_POST['plan_name'];
		$description = $_POST['description'];
		$price = $_POST['price'];
		$currency = $_POST['currency'];
		$billing_cycle = $_POST['billing_cycle'];
		$max_extensions = $_POST['max_extensions'];
		$max_gateways = $_POST['max_gateways'];
		$max_ivrs = $_POST['max_ivrs'];
		$max_call_recordings = $_POST['max_call_recordings'];
		$max_ring_groups = $_POST['max_ring_groups'];
		$features_json = $_POST['features_json'];
		$enabled = $_POST['enabled'];

		//build the array
		$array['v_billing_plans'][0]['plan_name'] = $plan_name;
		$array['v_billing_plans'][0]['description'] = $description;
		$array['v_billing_plans'][0]['price'] = $price;
		$array['v_billing_plans'][0]['currency'] = $currency;
		$array['v_billing_plans'][0]['billing_cycle'] = $billing_cycle;
		$array['v_billing_plans'][0]['max_extensions'] = $max_extensions;
		$array['v_billing_plans'][0]['max_gateways'] = $max_gateways;
		$array['v_billing_plans'][0]['max_ivrs'] = $max_ivrs;
		$array['v_billing_plans'][0]['max_call_recordings'] = $max_call_recordings;
		$array['v_billing_plans'][0]['max_ring_groups'] = $max_ring_groups;
		$array['v_billing_plans'][0]['features_json'] = $features_json;
		$array['v_billing_plans'][0]['enabled'] = $enabled;

		if ($action == 'add') {
			$plan_uuid = uuid();
			$array['v_billing_plans'][0]['plan_uuid'] = $plan_uuid;
			$array['v_billing_plans'][0]['add_date'] = date('Y-m-d H:i:s');
			$array['v_billing_plans'][0]['add_user'] = $_SESSION['user_uuid'];
		}
		else {
			$array['v_billing_plans'][0]['plan_uuid'] = $plan_uuid;
		}

		//save to the database using direct SQL for reliability
		if ($action == 'add') {
			$sql = "insert into v_billing_plans (plan_uuid, plan_name, description, price, currency, billing_cycle, max_extensions, max_gateways, max_ivrs, max_call_recordings, max_ring_groups, features_json, enabled, add_date, add_user) ";
			$sql .= "values (:plan_uuid, :plan_name, :description, :price, :currency, :billing_cycle, :max_extensions, :max_gateways, :max_ivrs, :max_call_recordings, :max_ring_groups, :features_json, :enabled, :add_date, :add_user) ";
			$parameters['add_date'] = date('Y-m-d H:i:s');
			$parameters['add_user'] = $_SESSION['user_uuid'];
		}
		else {
			$sql = "update v_billing_plans set plan_name = :plan_name, description = :description, price = :price, currency = :currency, billing_cycle = :billing_cycle, max_extensions = :max_extensions, max_gateways = :max_gateways, max_ivrs = :max_ivrs, max_call_recordings = :max_call_recordings, max_ring_groups = :max_ring_groups, features_json = :features_json, enabled = :enabled ";
			$sql .= "where plan_uuid = :plan_uuid ";
		}
		$parameters['plan_uuid'] = $plan_uuid;
		$parameters['plan_name'] = $plan_name;
		$parameters['description'] = $description;
		$parameters['price'] = $price;
		$parameters['currency'] = $currency;
		$parameters['billing_cycle'] = $billing_cycle;
		$parameters['max_extensions'] = $max_extensions;
		$parameters['max_gateways'] = $max_gateways;
		$parameters['max_ivrs'] = $max_ivrs;
		$parameters['max_call_recordings'] = $max_call_recordings;
		$parameters['max_ring_groups'] = $max_ring_groups;
		$parameters['features_json'] = $features_json;
		$parameters['enabled'] = $enabled;
		$database = new database;
		$database->execute($sql, $parameters);
		unset($sql, $parameters, $array);

		//redirect
		message::add($text['message-saved']);
		header('Location: billing_plans.php');
		exit;
	}

//pre-populate the form
	if ($action == 'update' && is_uuid($plan_uuid)) {
		$sql = "select * from v_billing_plans where plan_uuid = :plan_uuid ";
		$parameters['plan_uuid'] = $plan_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row)) {
			$plan_name = $row['plan_name'];
			$description = $row['description'];
			$price = $row['price'];
			$currency = $row['currency'];
			$billing_cycle = $row['billing_cycle'];
			$max_extensions = $row['max_extensions'];
			$max_gateways = $row['max_gateways'];
			$max_ivrs = $row['max_ivrs'];
			$max_call_recordings = $row['max_call_recordings'];
			$max_ring_groups = $row['max_ring_groups'];
			$features_json = $row['features_json'];
			$enabled = $row['enabled'];
		}
		unset($sql, $parameters, $row);
	}

//set defaults
	if (strlen($currency) == 0) { $currency = 'USD'; }
	if (strlen($billing_cycle) == 0) { $billing_cycle = 'monthly'; }
	if (strlen($enabled) == 0) { $enabled = 'true'; }

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-billing_plan_edit'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_plan_edit']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>'arrow-left','link'=>'billing_plans.php']);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>'check','id'=>'btn_save','form'=>'frm_edit']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_plan_edit']."\n";
	echo "<br /><br />\n";

	echo "<form id='frm_edit' method='post'>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top'>".$text['label-plan_name']."</td>\n";
	echo "<td width='70%' class='vtable'><input class='formfld' type='text' name='plan_name' value='".escape($plan_name)."' required='required'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-plan_description']."</td>\n";
	echo "<td class='vtable'><textarea class='formfld' name='description' rows='3'>".escape($description)."</textarea></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-plan_price']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='number' step='0.01' name='price' value='".escape($price)."' required='required'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-currency']."</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<select class='formfld' name='currency'>\n";
	echo "		<option value='USD' ".($currency == 'USD' ? "selected='selected'" : "").">USD</option>\n";
	echo "		<option value='EUR' ".($currency == 'EUR' ? "selected='selected'" : "").">EUR</option>\n";
	echo "		<option value='GBP' ".($currency == 'GBP' ? "selected='selected'" : "").">GBP</option>\n";
	echo "		<option value='SAR' ".($currency == 'SAR' ? "selected='selected'" : "").">SAR</option>\n";
	echo "		<option value='AED' ".($currency == 'AED' ? "selected='selected'" : "").">AED</option>\n";
	echo "		<option value='EGP' ".($currency == 'EGP' ? "selected='selected'" : "").">EGP</option>\n";
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-billing_cycle']."</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<select class='formfld' name='billing_cycle'>\n";
	echo "		<option value='monthly' ".($billing_cycle == 'monthly' ? "selected='selected'" : "").">".$text['option-monthly']."</option>\n";
	echo "		<option value='quarterly' ".($billing_cycle == 'quarterly' ? "selected='selected'" : "").">".$text['option-quarterly']."</option>\n";
	echo "		<option value='yearly' ".($billing_cycle == 'yearly' ? "selected='selected'" : "").">".$text['option-yearly']."</option>\n";
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-max_extensions']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='number' name='max_extensions' value='".escape($max_extensions)."'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-max_gateways']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='number' name='max_gateways' value='".escape($max_gateways)."'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-max_ivrs']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='number' name='max_ivrs' value='".escape($max_ivrs)."'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-max_call_recordings']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='number' name='max_call_recordings' value='".escape($max_call_recordings)."'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-max_ring_groups']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='number' name='max_ring_groups' value='".escape($max_ring_groups)."'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-features_json']."</td>\n";
	echo "<td class='vtable'><textarea class='formfld' name='features_json' rows='4' placeholder='{\"voicemail\": true, \"conference\": true}'>".escape($features_json)."</textarea></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-enabled']."</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<select class='formfld' name='enabled'>\n";
	echo "		<option value='true' ".($enabled == 'true' ? "selected='selected'" : "").">".$text['label-true']."</option>\n";
	echo "		<option value='false' ".($enabled == 'false' ? "selected='selected'" : "").">".$text['label-false']."</option>\n";
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
