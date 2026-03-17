<?php

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_credit_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get credits
	$sql = "select c.*, d.domain_name ";
	$sql .= "from v_billing_credits as c ";
	$sql .= "left join v_domains as d on c.domain_uuid = d.domain_uuid ";
	$sql .= "order by c.add_date desc ";
	$database = new database;
	$credits = $database->select($sql, null, 'all');
	unset($sql);

//include the header
	$document['title'] = $text['title-billing_credits'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_credits']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_credits']."\n";
	echo "<br /><br />\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "	<th>".$text['label-domain']."</th>\n";
	echo "	<th>".$text['label-credit_amount']."</th>\n";
	echo "	<th>".$text['label-currency']."</th>\n";
	echo "	<th>".$text['label-credit_description']."</th>\n";
	echo "	<th>".$text['label-transaction_type']."</th>\n";
	echo "	<th>".$text['label-add_date']."</th>\n";
	echo "</tr>\n";

	if (is_array($credits) && count($credits) > 0) {
		foreach ($credits as $row) {
			$type_color = $row['transaction_type'] == 'credit' ? '#4CAF50' : '#f44336';
			echo "<tr class='list-row'>\n";
			echo "	<td>".escape($row['domain_name'])."</td>\n";
			echo "	<td>".number_format($row['amount'], 2)."</td>\n";
			echo "	<td>".escape($row['currency'])."</td>\n";
			echo "	<td>".escape($row['description'])."</td>\n";
			echo "	<td><span style='background:".$type_color.";color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;'>".escape(ucfirst($row['transaction_type']))."</span></td>\n";
			echo "	<td>".escape($row['add_date'])."</td>\n";
			echo "</tr>\n";
		}
	}

	echo "</table>\n";

//include the footer
	require_once "resources/footer.php";

?>
