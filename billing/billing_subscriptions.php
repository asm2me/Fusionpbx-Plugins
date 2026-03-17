<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_subscription_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//handle delete
	if ($_REQUEST['action'] == 'delete' && permission_exists('billing_subscriptions_delete')) {
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative');
			header('Location: billing_subscriptions.php');
			exit;
		}
		$subscription_uuid = $_REQUEST['id'];
		if (is_uuid($subscription_uuid)) {
			$array['v_billing_subscriptions'][0]['subscription_uuid'] = $subscription_uuid;
			$p = new permissions;
			$p->add('v_billing_subscriptions_delete', 'temp');
			$database = new database;
			$database->app_name = 'billing';
			$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
			$database->delete($array);
			unset($array);
			$p->delete('v_billing_subscriptions_delete', 'temp');
			message::add($text['message-deleted']);
		}
		header('Location: billing_subscriptions.php');
		exit;
	}

//get status filter
	$status_filter = $_REQUEST['status'] ?? '';
	$parameters = array();
	$sql = "select s.*, d.domain_name, p.plan_name, p.price, p.currency ";
	$sql .= "from v_billing_subscriptions as s ";
	$sql .= "left join v_domains as d on s.domain_uuid = d.domain_uuid ";
	$sql .= "left join v_billing_plans as p on s.plan_uuid = p.plan_uuid ";
	if (!empty($status_filter)) {
		$sql .= "where s.status = :status ";
		$parameters['status'] = $status_filter;
	}
	$sql .= "order by s.add_date desc ";
	$database = new database;
	$subscriptions = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-billing_subscriptions'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_subscriptions']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('billing_subscriptions_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>'plus','link'=>'billing_subscription_edit.php']);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_subscriptions']."\n";
	echo "<br /><br />\n";

//status filter
	echo "<form method='get'>\n";
	echo "<div style='margin-bottom:15px;'>\n";
	echo "	<select class='formfld' name='status' onchange='this.form.submit();'>\n";
	echo "		<option value=''>-- All Statuses --</option>\n";
	echo "		<option value='active' ".($status_filter == 'active' ? "selected" : "").">".$text['option-active']."</option>\n";
	echo "		<option value='suspended' ".($status_filter == 'suspended' ? "selected" : "").">".$text['option-suspended']."</option>\n";
	echo "		<option value='cancelled' ".($status_filter == 'cancelled' ? "selected" : "").">".$text['option-cancelled']."</option>\n";
	echo "		<option value='expired' ".($status_filter == 'expired' ? "selected" : "").">".$text['option-expired']."</option>\n";
	echo "	</select>\n";
	echo "</div>\n";
	echo "</form>\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "	<th>".$text['label-domain']."</th>\n";
	echo "	<th>".$text['label-plan']."</th>\n";
	echo "	<th>".$text['label-plan_price']."</th>\n";
	echo "	<th>".$text['label-status']."</th>\n";
	echo "	<th>".$text['label-start_date']."</th>\n";
	echo "	<th>".$text['label-end_date']."</th>\n";
	echo "	<th>".$text['label-auto_renew']."</th>\n";
	if (permission_exists('billing_subscriptions_edit') || permission_exists('billing_subscriptions_delete')) {
		echo "	<th class='action-button'>&nbsp;</th>\n";
	}
	echo "</tr>\n";

	if (is_array($subscriptions) && count($subscriptions) > 0) {
		foreach ($subscriptions as $row) {
			$status_color = '#999';
			switch ($row['status']) {
				case 'active': $status_color = '#4CAF50'; break;
				case 'suspended': $status_color = '#f44336'; break;
				case 'cancelled': $status_color = '#9E9E9E'; break;
				case 'expired': $status_color = '#FF9800'; break;
			}
			echo "<tr class='list-row'>\n";
			if (permission_exists('billing_subscriptions_edit')) {
				echo "	<td><a href='billing_subscription_edit.php?id=".urlencode($row['subscription_uuid'])."'>".escape($row['domain_name'])."</a></td>\n";
			}
			else {
				echo "	<td>".escape($row['domain_name'])."</td>\n";
			}
			echo "	<td>".escape($row['plan_name'])."</td>\n";
			echo "	<td>".number_format($row['price'], 2)." ".escape($row['currency'])."</td>\n";
			echo "	<td><span style='background:".$status_color.";color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;'>".escape(ucfirst($row['status']))."</span></td>\n";
			echo "	<td>".escape($row['start_date'])."</td>\n";
			echo "	<td>".escape($row['end_date'])."</td>\n";
			echo "	<td>".escape($row['auto_renew'] == 'true' ? 'Yes' : 'No')."</td>\n";
			if (permission_exists('billing_subscriptions_edit') || permission_exists('billing_subscriptions_delete')) {
				echo "	<td class='action-button'>\n";
				if (permission_exists('billing_subscriptions_edit')) {
					echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>'pencil-alt','link'=>'billing_subscription_edit.php?id='.urlencode($row['subscription_uuid'])]);
				}
				if (permission_exists('billing_subscriptions_delete')) {
					echo button::create(['type'=>'button','title'=>$text['button-delete'],'icon'=>'trash','link'=>'billing_subscriptions.php?action=delete&id='.urlencode($row['subscription_uuid']).'&token='.$token,'onclick'=>"return confirm('".$text['confirm-delete']."');"]);
				}
				echo "	</td>\n";
			}
			echo "</tr>\n";
		}
	}

	echo "</table>\n";

//include the footer
	require_once "resources/footer.php";

?>
