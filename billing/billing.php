<?php

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_dashboard_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get dashboard stats
	require_once "resources/classes/billing.php";
	$billing = new billing;
	$stats = $billing->get_dashboard_stats();

//get recent payments
	$sql = "select p.*, i.invoice_number, d.domain_name ";
	$sql .= "from v_billing_payments as p ";
	$sql .= "left join v_billing_invoices as i on p.invoice_uuid = i.invoice_uuid ";
	$sql .= "left join v_domains as d on p.domain_uuid = d.domain_uuid ";
	$sql .= "order by p.add_date desc ";
	$sql .= "limit 10 ";
	$database = new database;
	$recent_payments = $database->select($sql, null, 'all');
	unset($sql);

//get upcoming expirations
	$sql = "select s.*, d.domain_name, p.plan_name ";
	$sql .= "from v_billing_subscriptions as s ";
	$sql .= "left join v_domains as d on s.domain_uuid = d.domain_uuid ";
	$sql .= "left join v_billing_plans as p on s.plan_uuid = p.plan_uuid ";
	$sql .= "where s.status = 'active' ";
	$sql .= "and s.end_date <= :expiry_date ";
	$sql .= "order by s.end_date asc ";
	$sql .= "limit 10 ";
	$parameters['expiry_date'] = date('Y-m-d H:i:s', strtotime('+30 days'));
	$database = new database;
	$upcoming_expirations = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//include the header
	$document['title'] = $text['title-billing_dashboard'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_dashboard']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_dashboard']."\n";
	echo "<br /><br />\n";

//stats cards
	echo "<div class='row' style='display:flex;flex-wrap:wrap;margin:0 -10px;'>\n";

	//active subscriptions
	echo "<div style='flex:1;min-width:200px;padding:10px;'>\n";
	echo "	<div style='background:#4CAF50;color:#fff;border-radius:8px;padding:20px;text-align:center;'>\n";
	echo "		<div style='font-size:36px;font-weight:bold;'>".($stats['active_subscriptions'] ?? 0)."</div>\n";
	echo "		<div style='font-size:14px;margin-top:5px;'><i class='fas fa-sync-alt'></i> ".$text['label-active_subscriptions']."</div>\n";
	echo "	</div>\n";
	echo "</div>\n";

	//revenue this month
	echo "<div style='flex:1;min-width:200px;padding:10px;'>\n";
	echo "	<div style='background:#2196F3;color:#fff;border-radius:8px;padding:20px;text-align:center;'>\n";
	echo "		<div style='font-size:36px;font-weight:bold;'>".number_format($stats['revenue_this_month'] ?? 0, 2)."</div>\n";
	echo "		<div style='font-size:14px;margin-top:5px;'><i class='fas fa-dollar-sign'></i> ".$text['label-revenue_this_month']."</div>\n";
	echo "	</div>\n";
	echo "</div>\n";

	//pending invoices
	echo "<div style='flex:1;min-width:200px;padding:10px;'>\n";
	echo "	<div style='background:#FF9800;color:#fff;border-radius:8px;padding:20px;text-align:center;'>\n";
	echo "		<div style='font-size:36px;font-weight:bold;'>".($stats['pending_invoices'] ?? 0)."</div>\n";
	echo "		<div style='font-size:14px;margin-top:5px;'><i class='fas fa-file-invoice'></i> ".$text['label-pending_invoices']."</div>\n";
	echo "	</div>\n";
	echo "</div>\n";

	//upcoming expirations
	echo "<div style='flex:1;min-width:200px;padding:10px;'>\n";
	echo "	<div style='background:#f44336;color:#fff;border-radius:8px;padding:20px;text-align:center;'>\n";
	echo "		<div style='font-size:36px;font-weight:bold;'>".($stats['upcoming_expirations'] ?? 0)."</div>\n";
	echo "		<div style='font-size:14px;margin-top:5px;'><i class='fas fa-exclamation-triangle'></i> ".$text['label-upcoming_expirations']."</div>\n";
	echo "	</div>\n";
	echo "</div>\n";

	echo "</div>\n";
	echo "<br />\n";

//upcoming expirations table
	echo "<div class='card' style='margin-bottom:20px;'>\n";
	echo "<b>".$text['label-upcoming_expirations']."</b>\n";
	echo "<br /><br />\n";
	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "	<th>".$text['label-domain']."</th>\n";
	echo "	<th>".$text['label-plan']."</th>\n";
	echo "	<th>".$text['label-end_date']."</th>\n";
	echo "	<th>".$text['label-status']."</th>\n";
	echo "</tr>\n";

	if (is_array($upcoming_expirations) && count($upcoming_expirations) > 0) {
		foreach ($upcoming_expirations as $row) {
			$days_left = floor((strtotime($row['end_date']) - time()) / 86400);
			$badge_color = $days_left <= 7 ? '#f44336' : ($days_left <= 14 ? '#FF9800' : '#4CAF50');
			echo "<tr class='list-row'>\n";
			echo "	<td><a href='billing_subscription_edit.php?id=".urlencode($row['subscription_uuid'])."'>".escape($row['domain_name'])."</a></td>\n";
			echo "	<td>".escape($row['plan_name'])."</td>\n";
			echo "	<td>".escape($row['end_date'])."</td>\n";
			echo "	<td><span style='background:".$badge_color.";color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;'>".$days_left." days</span></td>\n";
			echo "</tr>\n";
		}
	}
	else {
		echo "<tr><td colspan='4' style='text-align:center;padding:20px;'>No upcoming expirations.</td></tr>\n";
	}

	echo "</table>\n";
	echo "</div>\n";

//recent payments table
	echo "<div class='card'>\n";
	echo "<b>".$text['label-recent_payments']."</b>\n";
	echo "<br /><br />\n";
	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "	<th>".$text['label-invoice_number']."</th>\n";
	echo "	<th>".$text['label-domain']."</th>\n";
	echo "	<th>".$text['label-amount']."</th>\n";
	echo "	<th>".$text['label-payment_gateway']."</th>\n";
	echo "	<th>".$text['label-status']."</th>\n";
	echo "	<th>".$text['label-add_date']."</th>\n";
	echo "</tr>\n";

	if (is_array($recent_payments) && count($recent_payments) > 0) {
		foreach ($recent_payments as $row) {
			$status_color = '#999';
			switch ($row['status']) {
				case 'completed': $status_color = '#4CAF50'; break;
				case 'pending': $status_color = '#FF9800'; break;
				case 'failed': $status_color = '#f44336'; break;
				case 'refunded': $status_color = '#9C27B0'; break;
			}
			echo "<tr class='list-row'>\n";
			echo "	<td>".escape($row['invoice_number'])."</td>\n";
			echo "	<td>".escape($row['domain_name'])."</td>\n";
			echo "	<td>".number_format($row['amount'], 2)." ".escape($row['currency'])."</td>\n";
			echo "	<td>".escape(ucfirst($row['payment_gateway']))."</td>\n";
			echo "	<td><span style='background:".$status_color.";color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;'>".escape(ucfirst($row['status']))."</span></td>\n";
			echo "	<td>".escape($row['add_date'])."</td>\n";
			echo "</tr>\n";
		}
	}
	else {
		echo "<tr><td colspan='6' style='text-align:center;padding:20px;'>No recent payments.</td></tr>\n";
	}

	echo "</table>\n";
	echo "</div>\n";

//include the footer
	require_once "resources/footer.php";

?>
