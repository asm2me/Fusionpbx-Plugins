<?php

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_invoice_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//handle generate invoice
	if ($_REQUEST['action'] == 'generate' && permission_exists('billing_invoice_add')) {
		$token_obj = new token;
		if ($token_obj->validate($_SERVER['PHP_SELF'])) {
			$subscription_uuid = $_REQUEST['subscription_uuid'];
			if (is_uuid($subscription_uuid)) {
				require_once "resources/classes/billing.php";
				$billing = new billing;
				$result = $billing->generate_invoice($subscription_uuid);
				if ($result) {
					message::add($text['message-saved']);
				}
				else {
					message::add($text['message-error'], 'negative');
				}
			}
		}
		header('Location: billing_invoices.php');
		exit;
	}

//get filters
	$status_filter = $_REQUEST['status'] ?? '';
	$date_from = $_REQUEST['date_from'] ?? '';
	$date_to = $_REQUEST['date_to'] ?? '';

	$parameters = array();
	$where_clauses = array();

	if (!empty($status_filter)) {
		$where_clauses[] = "i.status = :status";
		$parameters['status'] = $status_filter;
	}
	if (!empty($date_from)) {
		$where_clauses[] = "i.add_date >= :date_from";
		$parameters['date_from'] = $date_from;
	}
	if (!empty($date_to)) {
		$where_clauses[] = "i.add_date <= :date_to";
		$parameters['date_to'] = $date_to . ' 23:59:59';
	}

	$sql = "select i.*, d.domain_name, s.status as subscription_status ";
	$sql .= "from v_billing_invoices as i ";
	$sql .= "left join v_domains as d on i.domain_uuid = d.domain_uuid ";
	$sql .= "left join v_billing_subscriptions as s on i.subscription_uuid = s.subscription_uuid ";
	if (count($where_clauses) > 0) {
		$sql .= "where ".implode(" and ", $where_clauses)." ";
	}
	$sql .= "order by i.add_date desc ";
	$database = new database;
	$invoices = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-billing_invoices'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_invoices']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_invoices']."\n";
	echo "<br /><br />\n";

//filters
	echo "<form method='get'>\n";
	echo "<div style='margin-bottom:15px;display:flex;gap:10px;flex-wrap:wrap;'>\n";
	echo "	<select class='formfld' name='status'>\n";
	echo "		<option value=''>-- All Statuses --</option>\n";
	echo "		<option value='pending' ".($status_filter == 'pending' ? "selected" : "").">".$text['option-pending']."</option>\n";
	echo "		<option value='paid' ".($status_filter == 'paid' ? "selected" : "").">".$text['option-paid']."</option>\n";
	echo "		<option value='overdue' ".($status_filter == 'overdue' ? "selected" : "").">".$text['option-overdue']."</option>\n";
	echo "		<option value='cancelled' ".($status_filter == 'cancelled' ? "selected" : "").">".$text['option-cancelled']."</option>\n";
	echo "	</select>\n";
	echo "	<input class='formfld' type='date' name='date_from' value='".escape($date_from)."' placeholder='From'>\n";
	echo "	<input class='formfld' type='date' name='date_to' value='".escape($date_to)."' placeholder='To'>\n";
	echo "	".button::create(['type'=>'submit','label'=>$text['button-search'],'icon'=>'search'])."\n";
	echo "</div>\n";
	echo "</form>\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "	<th>".$text['label-invoice_number']."</th>\n";
	echo "	<th>".$text['label-domain']."</th>\n";
	echo "	<th>".$text['label-amount']."</th>\n";
	echo "	<th>".$text['label-tax_amount']."</th>\n";
	echo "	<th>".$text['label-total_amount']."</th>\n";
	echo "	<th>".$text['label-status']."</th>\n";
	echo "	<th>".$text['label-due_date']."</th>\n";
	echo "	<th>".$text['label-paid_date']."</th>\n";
	echo "	<th class='action-button'>&nbsp;</th>\n";
	echo "</tr>\n";

	if (is_array($invoices) && count($invoices) > 0) {
		foreach ($invoices as $row) {
			$status_color = '#999';
			switch ($row['status']) {
				case 'paid': $status_color = '#4CAF50'; break;
				case 'pending': $status_color = '#FF9800'; break;
				case 'overdue': $status_color = '#f44336'; break;
				case 'cancelled': $status_color = '#9E9E9E'; break;
			}
			echo "<tr class='list-row'>\n";
			echo "	<td><a href='billing_invoice_view.php?id=".urlencode($row['invoice_uuid'])."'>".escape($row['invoice_number'])."</a></td>\n";
			echo "	<td>".escape($row['domain_name'])."</td>\n";
			echo "	<td>".number_format($row['amount'], 2)." ".escape($row['currency'])."</td>\n";
			echo "	<td>".number_format($row['tax_amount'], 2)."</td>\n";
			echo "	<td>".number_format($row['total_amount'], 2)."</td>\n";
			echo "	<td><span style='background:".$status_color.";color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;'>".escape(ucfirst($row['status']))."</span></td>\n";
			echo "	<td>".escape(substr($row['due_date'], 0, 10))."</td>\n";
			echo "	<td>".escape($row['paid_date'] ? substr($row['paid_date'], 0, 10) : '-')."</td>\n";
			echo "	<td class='action-button'>\n";
			echo button::create(['type'=>'button','title'=>'View','icon'=>'eye','link'=>'billing_invoice_view.php?id='.urlencode($row['invoice_uuid'])]);
			echo "	</td>\n";
			echo "</tr>\n";
		}
	}

	echo "</table>\n";

//include the footer
	require_once "resources/footer.php";

?>
