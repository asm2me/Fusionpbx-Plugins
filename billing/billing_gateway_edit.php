<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_gateway_add') && !permission_exists('billing_gateway_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//action add or update
	if (is_uuid($_REQUEST['id'])) {
		$gateway_uuid = $_REQUEST['id'];
		$action = 'update';
	}
	else {
		$action = 'add';
	}

//handle test connection
	if ($_REQUEST['action'] == 'test' && is_uuid($_REQUEST['id'])) {
		$gateway_uuid = $_REQUEST['id'];
		$sql = "select * from v_billing_payment_gateways where gateway_uuid = :gateway_uuid ";
		$parameters['gateway_uuid'] = $gateway_uuid;
		$database = new database;
		$gw = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		$test_result = false;
		if (is_array($gw)) {
			switch ($gw['gateway_name']) {
				case 'paypal':
					require_once __DIR__ . "/resources/classes/billing_paypal.php";
					$paypal = new billing_paypal;
					$test_result = $paypal->test_connection($gw);
					break;
				case 'stripe':
					require_once __DIR__ . "/resources/classes/billing_stripe.php";
					$stripe = new billing_stripe;
					$test_result = $stripe->test_connection($gw);
					break;
				case 'binance':
					require_once __DIR__ . "/resources/classes/billing_binance.php";
					$binance = new billing_binance;
					$test_result = $binance->test_connection($gw);
					break;
			}
		}

		if ($test_result) {
			message::add($text['message-connection_success']);
		}
		else {
			message::add($text['message-connection_failed'], 'negative');
		}
		header('Location: billing_gateway_edit.php?id='.$gateway_uuid);
		exit;
	}

//get http post variables and save
	if (count($_POST) > 0 && strlen($_POST['gateway_name']) > 0) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative');
			header('Location: billing_gateways.php');
			exit;
		}

		$gateway_name = $_POST['gateway_name'];
		$display_name = $_POST['display_name'];
		$sandbox_mode = $_POST['sandbox_mode'];
		$enabled = $_POST['enabled'];

		//encrypt sensitive data
		$encryption_key = $_SESSION['encryption']['key'] ?? 'billing_default_key';

		//build the array
		$array['billing_payment_gateways'][0]['gateway_name'] = $gateway_name;
		$array['billing_payment_gateways'][0]['display_name'] = $display_name;
		$array['billing_payment_gateways'][0]['sandbox_mode'] = $sandbox_mode;
		$array['billing_payment_gateways'][0]['enabled'] = $enabled;

		//gateway-specific fields stored in config_json
		$config = array();
		switch ($gateway_name) {
			case 'paypal':
				$config['client_id'] = $_POST['client_id'];
				$config['client_secret'] = $_POST['client_secret'];
				$array['billing_payment_gateways'][0]['api_key_encrypted'] = base64_encode(openssl_encrypt($_POST['client_id'], 'aes-256-cbc', $encryption_key, 0, str_pad('', 16, "\0")));
				$array['billing_payment_gateways'][0]['api_secret_encrypted'] = base64_encode(openssl_encrypt($_POST['client_secret'], 'aes-256-cbc', $encryption_key, 0, str_pad('', 16, "\0")));
				break;
			case 'stripe':
				$config['publishable_key'] = $_POST['publishable_key'];
				$config['secret_key'] = $_POST['secret_key'];
				$config['webhook_secret'] = $_POST['webhook_secret'];
				$array['billing_payment_gateways'][0]['api_key_encrypted'] = base64_encode(openssl_encrypt($_POST['publishable_key'], 'aes-256-cbc', $encryption_key, 0, str_pad('', 16, "\0")));
				$array['billing_payment_gateways'][0]['api_secret_encrypted'] = base64_encode(openssl_encrypt($_POST['secret_key'], 'aes-256-cbc', $encryption_key, 0, str_pad('', 16, "\0")));
				$array['billing_payment_gateways'][0]['webhook_secret'] = $_POST['webhook_secret'];
				break;
			case 'binance':
				$config['api_key'] = $_POST['api_key'];
				$config['api_secret'] = $_POST['api_secret'];
				$config['merchant_id'] = $_POST['merchant_id'];
				$array['billing_payment_gateways'][0]['api_key_encrypted'] = base64_encode(openssl_encrypt($_POST['api_key'], 'aes-256-cbc', $encryption_key, 0, str_pad('', 16, "\0")));
				$array['billing_payment_gateways'][0]['api_secret_encrypted'] = base64_encode(openssl_encrypt($_POST['api_secret'], 'aes-256-cbc', $encryption_key, 0, str_pad('', 16, "\0")));
				break;
		}
		$array['billing_payment_gateways'][0]['config_json'] = json_encode($config);

		if ($action == 'add') {
			$gateway_uuid = uuid();
			$array['billing_payment_gateways'][0]['gateway_uuid'] = $gateway_uuid;
			$array['billing_payment_gateways'][0]['add_date'] = date('Y-m-d H:i:s');
		}
		else {
			$array['billing_payment_gateways'][0]['gateway_uuid'] = $gateway_uuid;
		}

		$p = new permissions;
		$p->add('billing_gateway_'.($action == 'add' ? 'add' : 'edit'), 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array);
		unset($array);
		$p->delete('billing_gateway_'.($action == 'add' ? 'add' : 'edit'), 'temp');

		message::add($text['message-saved']);
		header('Location: billing_gateways.php');
		exit;
	}

//pre-populate the form
	$config = array();
	if ($action == 'update' && is_uuid($gateway_uuid)) {
		$sql = "select * from v_billing_payment_gateways where gateway_uuid = :gateway_uuid ";
		$parameters['gateway_uuid'] = $gateway_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row)) {
			$gateway_name = $row['gateway_name'];
			$display_name = $row['display_name'];
			$sandbox_mode = $row['sandbox_mode'];
			$enabled = $row['enabled'];
			$config = json_decode($row['config_json'], true) ?? array();
		}
		unset($sql, $parameters, $row);
	}

//set defaults
	if (strlen($gateway_name) == 0) { $gateway_name = 'paypal'; }
	if (strlen($sandbox_mode) == 0) { $sandbox_mode = 'true'; }
	if (strlen($enabled) == 0) { $enabled = 'true'; }

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-billing_gateway_edit'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_gateway_edit']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>'arrow-left','link'=>'billing_gateways.php']);
	if ($action == 'update') {
		echo "		<button type='button' class='btn btn-default' onclick=\"window.location='billing_gateway_edit.php?action=test&id=".escape($gateway_uuid)."';\"><i class='fas fa-plug'></i> ".$text['button-test_connection']."</button>\n";
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>'check','id'=>'btn_save','form'=>'frm_edit']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_gateway_edit']."\n";
	echo "<br /><br />\n";

	echo "<form id='frm_edit' method='post'>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top'>".$text['label-gateway_name']."</td>\n";
	echo "<td width='70%' class='vtable'>\n";
	echo "	<select class='formfld' name='gateway_name' id='gateway_name' onchange='toggleGatewayFields();'>\n";
	echo "		<option value='paypal' ".($gateway_name == 'paypal' ? "selected='selected'" : "").">PayPal</option>\n";
	echo "		<option value='stripe' ".($gateway_name == 'stripe' ? "selected='selected'" : "").">Stripe</option>\n";
	echo "		<option value='binance' ".($gateway_name == 'binance' ? "selected='selected'" : "").">Binance Pay</option>\n";
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-display_name']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='text' name='display_name' value='".escape($display_name)."' required='required'></td>\n";
	echo "</tr>\n";

	//PayPal fields
	echo "<tbody id='paypal_fields' style='display:".($gateway_name == 'paypal' ? 'table-row-group' : 'none').";'>\n";
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-client_id']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='text' name='client_id' value='".escape($config['client_id'] ?? '')."'></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-client_secret']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='password' name='client_secret' value='".escape($config['client_secret'] ?? '')."'></td>\n";
	echo "</tr>\n";
	echo "</tbody>\n";

	//Stripe fields
	echo "<tbody id='stripe_fields' style='display:".($gateway_name == 'stripe' ? 'table-row-group' : 'none').";'>\n";
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-publishable_key']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='text' name='publishable_key' value='".escape($config['publishable_key'] ?? '')."'></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-secret_key']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='password' name='secret_key' value='".escape($config['secret_key'] ?? '')."'></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-webhook_secret']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='password' name='webhook_secret' value='".escape($config['webhook_secret'] ?? '')."'></td>\n";
	echo "</tr>\n";
	echo "</tbody>\n";

	//Binance fields
	echo "<tbody id='binance_fields' style='display:".($gateway_name == 'binance' ? 'table-row-group' : 'none').";'>\n";
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-api_key']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='text' name='api_key' value='".escape($config['api_key'] ?? '')."'></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-api_secret']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='password' name='api_secret' value='".escape($config['api_secret'] ?? '')."'></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-merchant_id']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='text' name='merchant_id' value='".escape($config['merchant_id'] ?? '')."'></td>\n";
	echo "</tr>\n";
	echo "</tbody>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-sandbox_mode']."</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<select class='formfld' name='sandbox_mode'>\n";
	echo "		<option value='true' ".($sandbox_mode == 'true' ? "selected='selected'" : "").">".$text['label-true']."</option>\n";
	echo "		<option value='false' ".($sandbox_mode == 'false' ? "selected='selected'" : "").">".$text['label-false']."</option>\n";
	echo "	</select>\n";
	echo "</td>\n";
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

//toggle gateway fields javascript
	echo "<script>\n";
	echo "function toggleGatewayFields() {\n";
	echo "	var gw = document.getElementById('gateway_name').value;\n";
	echo "	document.getElementById('paypal_fields').style.display = (gw == 'paypal') ? 'table-row-group' : 'none';\n";
	echo "	document.getElementById('stripe_fields').style.display = (gw == 'stripe') ? 'table-row-group' : 'none';\n";
	echo "	document.getElementById('binance_fields').style.display = (gw == 'binance') ? 'table-row-group' : 'none';\n";
	echo "}\n";
	echo "</script>\n";

//include the footer
	require_once "resources/footer.php";

?>
