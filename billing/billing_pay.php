<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_pay_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get the domain uuid
	$domain_uuid = $_SESSION['domain_uuid'];

//get current subscription
	$sql = "select s.*, p.plan_name, p.price, p.currency, p.billing_cycle, d.domain_name ";
	$sql .= "from v_billing_subscriptions as s ";
	$sql .= "left join v_billing_plans as p on s.plan_uuid = p.plan_uuid ";
	$sql .= "left join v_domains as d on s.domain_uuid = d.domain_uuid ";
	$sql .= "where s.domain_uuid = :domain_uuid ";
	$sql .= "order by s.add_date desc limit 1 ";
	$parameters['domain_uuid'] = $domain_uuid;
	$database = new database;
	$subscription = $database->select($sql, $parameters, 'row');
	unset($sql, $parameters);

//get pending invoices for this domain
	$sql = "select * from v_billing_invoices ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "and status in ('pending', 'overdue') ";
	$sql .= "order by due_date asc ";
	$parameters['domain_uuid'] = $domain_uuid;
	$database = new database;
	$pending_invoices = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//get enabled payment gateways
	$sql = "select * from v_billing_payment_gateways where enabled = 'true' order by gateway_name asc ";
	$database = new database;
	$gateways = $database->select($sql, null, 'all');
	unset($sql);

//handle payment initiation
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['invoice_uuid']) && !empty($_POST['gateway'])) {
		$token_obj = new token;
		if (!$token_obj->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative');
			header('Location: billing_pay.php');
			exit;
		}

		$invoice_uuid = $_POST['invoice_uuid'];
		$gateway_name = $_POST['gateway'];

		if (is_uuid($invoice_uuid)) {
			require_once __DIR__ . "/resources/classes/billing.php";
			$billing = new billing;
			$result = $billing->process_payment($invoice_uuid, $gateway_name, $_POST);

			if ($result && isset($result['redirect_url'])) {
				header('Location: '.$result['redirect_url']);
				exit;
			}
			elseif ($result && $result['status'] == 'completed') {
				message::add($text['message-payment_success']);
				header('Location: billing_pay.php');
				exit;
			}
			else {
				message::add($text['message-payment_failed'], 'negative');
				header('Location: billing_pay.php');
				exit;
			}
		}
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-billing_pay'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_pay']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_pay']."\n";
	echo "<br /><br />\n";

//current subscription status
	if (is_array($subscription)) {
		$status_color = '#999';
		switch ($subscription['status']) {
			case 'active': $status_color = '#4CAF50'; break;
			case 'suspended': $status_color = '#f44336'; break;
			case 'cancelled': $status_color = '#9E9E9E'; break;
			case 'expired': $status_color = '#FF9800'; break;
		}

		echo "<div style='background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;'>\n";
		echo "	<h3 style='margin-top:0;'>Current Subscription</h3>\n";
		echo "	<table style='width:100%;'>\n";
		echo "	<tr><td style='width:150px;font-weight:bold;padding:5px 0;'>".$text['label-domain'].":</td><td>".escape($subscription['domain_name'])."</td></tr>\n";
		echo "	<tr><td style='font-weight:bold;padding:5px 0;'>".$text['label-plan'].":</td><td>".escape($subscription['plan_name'])."</td></tr>\n";
		echo "	<tr><td style='font-weight:bold;padding:5px 0;'>".$text['label-plan_price'].":</td><td>".number_format($subscription['price'], 2)." ".escape($subscription['currency'])." / ".escape($subscription['billing_cycle'])."</td></tr>\n";
		echo "	<tr><td style='font-weight:bold;padding:5px 0;'>".$text['label-status'].":</td><td><span style='background:".$status_color.";color:#fff;padding:2px 8px;border-radius:4px;'>".escape(ucfirst($subscription['status']))."</span></td></tr>\n";
		echo "	<tr><td style='font-weight:bold;padding:5px 0;'>".$text['label-end_date'].":</td><td>".escape(substr($subscription['end_date'], 0, 10))."</td></tr>\n";
		echo "	</table>\n";
		echo "</div>\n";
	}

//pending invoices
	if (is_array($pending_invoices) && count($pending_invoices) > 0) {
		echo "<div style='background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;'>\n";
		echo "	<h3 style='margin-top:0;'>Pending Invoices</h3>\n";

		foreach ($pending_invoices as $inv) {
			echo "<div style='border:1px solid #eee;border-radius:4px;padding:15px;margin-bottom:15px;'>\n";
			echo "	<div style='display:flex;justify-content:space-between;align-items:center;'>\n";
			echo "		<div>\n";
			echo "			<strong>".escape($inv['invoice_number'])."</strong><br>\n";
			echo "			<span style='color:#666;'>Due: ".escape(substr($inv['due_date'], 0, 10))."</span><br>\n";
			echo "			<span style='font-size:20px;font-weight:bold;color:#333;'>".number_format($inv['total_amount'], 2)." ".escape($inv['currency'])."</span>\n";
			echo "		</div>\n";
			echo "		<div>\n";

			//payment form
			echo "			<form method='post' style='display:inline;'>\n";
			echo "			<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
			echo "			<input type='hidden' name='invoice_uuid' value='".escape($inv['invoice_uuid'])."'>\n";
			echo "			<div style='margin-bottom:10px;'>\n";
			echo "				<select class='formfld' name='gateway' required='required' style='min-width:150px;'>\n";
			echo "					<option value=''>".$text['label-select_gateway']."</option>\n";
			if (is_array($gateways)) {
				foreach ($gateways as $gw) {
					echo "					<option value='".escape($gw['gateway_name'])."'>".escape($gw['display_name'])."</option>\n";
				}
			}
			echo "				</select>\n";
			echo "			</div>\n";
			echo "			<button type='submit' class='btn btn-primary' style='background:#4CAF50;color:#fff;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;'>\n";
			echo "				<i class='fas fa-credit-card'></i> ".$text['button-pay_now']."\n";
			echo "			</button>\n";
			echo "			</form>\n";

			echo "		</div>\n";
			echo "	</div>\n";
			echo "</div>\n";
		}

		echo "</div>\n";
	}
	else {
		echo "<div style='text-align:center;padding:40px;color:#666;'>\n";
		echo "	<i class='fas fa-check-circle' style='font-size:48px;color:#4CAF50;'></i><br><br>\n";
		echo "	".$text['message-no_pending_invoices']."\n";
		echo "</div>\n";
	}

//include the footer
	require_once "resources/footer.php";

?>
