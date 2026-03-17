<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_invoice_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get the invoice uuid
	$invoice_uuid = $_REQUEST['id'];
	if (!is_uuid($invoice_uuid)) {
		header('Location: billing_invoices.php');
		exit;
	}

//get invoice details
	$sql = "select i.*, d.domain_name, s.status as subscription_status, ";
	$sql .= "p.plan_name, p.billing_cycle ";
	$sql .= "from v_billing_invoices as i ";
	$sql .= "left join v_domains as d on i.domain_uuid = d.domain_uuid ";
	$sql .= "left join v_billing_subscriptions as s on i.subscription_uuid = s.subscription_uuid ";
	$sql .= "left join v_billing_plans as p on s.plan_uuid = p.plan_uuid ";
	$sql .= "where i.invoice_uuid = :invoice_uuid ";
	$parameters['invoice_uuid'] = $invoice_uuid;
	$database = new database;
	$invoice = $database->select($sql, $parameters, 'row');
	unset($sql, $parameters);

	if (!is_array($invoice)) {
		header('Location: billing_invoices.php');
		exit;
	}

//get payments for this invoice
	$sql = "select * from v_billing_payments where invoice_uuid = :invoice_uuid order by add_date desc ";
	$parameters['invoice_uuid'] = $invoice_uuid;
	$database = new database;
	$payments = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//get company info from default settings
	$company_name = $_SESSION['billing']['company_name']['text'] ?? '';
	$company_address = $_SESSION['billing']['company_address']['text'] ?? '';

//include the header
	$document['title'] = $text['title-billing_invoice_view'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_invoice_view']." - ".escape($invoice['invoice_number'])."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>'arrow-left','link'=>'billing_invoices.php']);
	echo "		<button type='button' class='btn btn-default' onclick='window.print();'><i class='fas fa-print'></i> ".$text['button-print']."</button>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

//invoice print-friendly layout
	echo "<div id='invoice-container' style='background:#fff;border:1px solid #ddd;border-radius:8px;padding:30px;max-width:800px;margin:20px auto;'>\n";

	//header
	echo "<div style='display:flex;justify-content:space-between;margin-bottom:30px;'>\n";
	echo "	<div>\n";
	echo "		<h2 style='margin:0;color:#333;'>".escape($company_name ? $company_name : 'INVOICE')."</h2>\n";
	if (!empty($company_address)) {
		echo "		<p style='color:#666;'>".nl2br(escape($company_address))."</p>\n";
	}
	echo "	</div>\n";
	echo "	<div style='text-align:right;'>\n";
	echo "		<h3 style='margin:0;color:#333;'>Invoice #".escape($invoice['invoice_number'])."</h3>\n";
	echo "		<p style='color:#666;'>Date: ".escape(substr($invoice['add_date'], 0, 10))."</p>\n";
	echo "		<p style='color:#666;'>Due: ".escape(substr($invoice['due_date'], 0, 10))."</p>\n";
	$status_color = '#999';
	switch ($invoice['status']) {
		case 'paid': $status_color = '#4CAF50'; break;
		case 'pending': $status_color = '#FF9800'; break;
		case 'overdue': $status_color = '#f44336'; break;
		case 'cancelled': $status_color = '#9E9E9E'; break;
	}
	echo "		<span style='background:".$status_color.";color:#fff;padding:4px 12px;border-radius:4px;font-weight:bold;'>".strtoupper($invoice['status'])."</span>\n";
	echo "	</div>\n";
	echo "</div>\n";

	//bill to
	echo "<div style='margin-bottom:30px;padding:15px;background:#f9f9f9;border-radius:4px;'>\n";
	echo "	<strong>Bill To:</strong><br>\n";
	echo "	Domain: ".escape($invoice['domain_name'])."<br>\n";
	echo "	Plan: ".escape($invoice['plan_name'])." (".escape(ucfirst($invoice['billing_cycle'])).")\n";
	echo "</div>\n";

	//line items
	echo "<table style='width:100%;border-collapse:collapse;margin-bottom:20px;'>\n";
	echo "<tr style='background:#f5f5f5;'>\n";
	echo "	<th style='padding:10px;text-align:left;border-bottom:2px solid #ddd;'>Description</th>\n";
	echo "	<th style='padding:10px;text-align:right;border-bottom:2px solid #ddd;'>Amount</th>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td style='padding:10px;border-bottom:1px solid #eee;'>".escape($invoice['plan_name'])." - ".escape(ucfirst($invoice['billing_cycle']))." Subscription</td>\n";
	echo "	<td style='padding:10px;text-align:right;border-bottom:1px solid #eee;'>".number_format($invoice['amount'], 2)." ".escape($invoice['currency'])."</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td style='padding:10px;text-align:right;font-weight:bold;'>Subtotal:</td>\n";
	echo "	<td style='padding:10px;text-align:right;'>".number_format($invoice['amount'], 2)." ".escape($invoice['currency'])."</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td style='padding:10px;text-align:right;font-weight:bold;'>Tax:</td>\n";
	echo "	<td style='padding:10px;text-align:right;'>".number_format($invoice['tax_amount'], 2)." ".escape($invoice['currency'])."</td>\n";
	echo "</tr>\n";
	echo "<tr style='background:#f5f5f5;'>\n";
	echo "	<td style='padding:10px;text-align:right;font-weight:bold;font-size:16px;'>Total:</td>\n";
	echo "	<td style='padding:10px;text-align:right;font-weight:bold;font-size:16px;'>".number_format($invoice['total_amount'], 2)." ".escape($invoice['currency'])."</td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	//payment info
	if ($invoice['paid_date']) {
		echo "<div style='padding:15px;background:#e8f5e9;border-radius:4px;margin-bottom:20px;'>\n";
		echo "	<strong>Paid:</strong> ".escape(substr($invoice['paid_date'], 0, 10))."<br>\n";
		echo "	<strong>Method:</strong> ".escape(ucfirst($invoice['payment_method']))."<br>\n";
		if (!empty($invoice['payment_reference'])) {
			echo "	<strong>Reference:</strong> ".escape($invoice['payment_reference'])."\n";
		}
		echo "</div>\n";
	}

	echo "</div>\n";

//payments history for this invoice
	if (is_array($payments) && count($payments) > 0) {
		echo "<br />\n";
		echo "<b>".$text['title-billing_payments']."</b>\n";
		echo "<br /><br />\n";
		echo "<table class='list'>\n";
		echo "<tr class='list-header'>\n";
		echo "	<th>".$text['label-payment_gateway']."</th>\n";
		echo "	<th>".$text['label-amount']."</th>\n";
		echo "	<th>".$text['label-transaction_id']."</th>\n";
		echo "	<th>".$text['label-status']."</th>\n";
		echo "	<th>".$text['label-add_date']."</th>\n";
		echo "</tr>\n";
		foreach ($payments as $prow) {
			$pstatus_color = $prow['status'] == 'completed' ? '#4CAF50' : ($prow['status'] == 'failed' ? '#f44336' : '#FF9800');
			echo "<tr class='list-row'>\n";
			echo "	<td>".escape(ucfirst($prow['payment_gateway']))."</td>\n";
			echo "	<td>".number_format($prow['amount'], 2)." ".escape($prow['currency'])."</td>\n";
			echo "	<td>".escape($prow['transaction_id'])."</td>\n";
			echo "	<td><span style='background:".$pstatus_color.";color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;'>".escape(ucfirst($prow['status']))."</span></td>\n";
			echo "	<td>".escape($prow['add_date'])."</td>\n";
			echo "</tr>\n";
		}
		echo "</table>\n";
	}

//print styles
	echo "<style>@media print { .action_bar, .menu-side { display:none !important; } #invoice-container { border:none !important; } body { background:#fff !important; } }</style>\n";

//include the footer
	require_once "resources/footer.php";

?>
