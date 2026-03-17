<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_payment_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get payments
	$sql = "select p.*, i.invoice_number, d.domain_name ";
	$sql .= "from v_billing_payments as p ";
	$sql .= "left join v_billing_invoices as i on p.invoice_uuid = i.invoice_uuid ";
	$sql .= "left join v_domains as d on p.domain_uuid = d.domain_uuid ";
	$sql .= "order by p.add_date desc ";
	$database = new database;
	$payments = $database->select($sql, null, 'all');
	unset($sql);

//include the header
	$document['title'] = $text['title-billing_payments'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_payments']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_payments']."\n";
	echo "<br /><br />\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "	<th>".$text['label-invoice_number']."</th>\n";
	echo "	<th>".$text['label-domain']."</th>\n";
	echo "	<th>".$text['label-amount']."</th>\n";
	echo "	<th>".$text['label-currency']."</th>\n";
	echo "	<th>".$text['label-payment_gateway']."</th>\n";
	echo "	<th>".$text['label-transaction_id']."</th>\n";
	echo "	<th>".$text['label-status']."</th>\n";
	echo "	<th>".$text['label-add_date']."</th>\n";
	echo "</tr>\n";

	if (is_array($payments) && count($payments) > 0) {
		foreach ($payments as $row) {
			$status_color = '#999';
			switch ($row['status']) {
				case 'completed': $status_color = '#4CAF50'; break;
				case 'pending': $status_color = '#FF9800'; break;
				case 'failed': $status_color = '#f44336'; break;
				case 'refunded': $status_color = '#9C27B0'; break;
			}
			echo "<tr class='list-row'>\n";
			echo "	<td>";
			if (is_uuid($row['invoice_uuid'])) {
				echo "<a href='billing_invoice_view.php?id=".urlencode($row['invoice_uuid'])."'>".escape($row['invoice_number'])."</a>";
			}
			else {
				echo escape($row['invoice_number']);
			}
			echo "</td>\n";
			echo "	<td>".escape($row['domain_name'])."</td>\n";
			echo "	<td>".number_format($row['amount'], 2)."</td>\n";
			echo "	<td>".escape($row['currency'])."</td>\n";
			echo "	<td>".escape(ucfirst($row['payment_gateway']))."</td>\n";
			echo "	<td style='max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;'>".escape($row['transaction_id'])."</td>\n";
			echo "	<td><span style='background:".$status_color.";color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;'>".escape(ucfirst($row['status']))."</span></td>\n";
			echo "	<td>".escape($row['add_date'])."</td>\n";
			echo "</tr>\n";
		}
	}

	echo "</table>\n";

//include the footer
	require_once "resources/footer.php";

?>
