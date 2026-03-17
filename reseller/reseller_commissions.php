<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('reseller_commissions_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//include the reseller class
	require_once __DIR__ . "/resources/classes/reseller.php";
	$reseller_obj = new reseller;

//handle payout request
	if (isset($_POST['request_payout']) && $_POST['request_payout'] === 'true') {
		$token_check = new token;
		if ($token_check->validate($_SERVER['PHP_SELF'])) {
			//log the payout request
			$payout_reseller_uuid = '';
			if (permission_exists('reseller_all') && isset($_POST['reseller_uuid']) && is_uuid($_POST['reseller_uuid'])) {
				$payout_reseller_uuid = $_POST['reseller_uuid'];
			} else {
				$profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
				$payout_reseller_uuid = $profile ? $profile['reseller_uuid'] : '';
			}
			if (!empty($payout_reseller_uuid)) {
				$reseller_obj->log_activity($payout_reseller_uuid, 'payout_requested', [
					'requested_by' => $_SESSION['user_uuid'],
				]);
				message::add($text['message-payout_requested']);
			}
		}
		header('Location: reseller_commissions.php');
		exit;
	}

//determine scope
	$is_admin = permission_exists('reseller_all');
	$filter_reseller_uuid = $_GET['reseller_uuid'] ?? '';
	$date_from = $_GET['date_from'] ?? '';
	$date_to = $_GET['date_to'] ?? '';
	$filter_status = $_GET['status'] ?? '';

//build query
	$sql = "select rc.*, d.domain_name, rp.company_name as reseller_name ";
	$sql .= "from v_reseller_commissions rc ";
	$sql .= "left join v_domains d on rc.domain_uuid = d.domain_uuid ";
	$sql .= "left join v_reseller_profiles rp on rc.reseller_uuid = rp.reseller_uuid ";
	$sql .= "where 1=1 ";
	$parameters = [];

	if (!$is_admin) {
		$profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
		if ($profile) {
			$sql .= "and rc.reseller_uuid = :reseller_uuid ";
			$parameters['reseller_uuid'] = $profile['reseller_uuid'];
			$reseller_uuid = $profile['reseller_uuid'];
		} else {
			$sql .= "and 1=0 "; //no results
		}
	} elseif (!empty($filter_reseller_uuid) && is_uuid($filter_reseller_uuid)) {
		$sql .= "and rc.reseller_uuid = :reseller_uuid ";
		$parameters['reseller_uuid'] = $filter_reseller_uuid;
		$reseller_uuid = $filter_reseller_uuid;
	}

	if (!empty($date_from)) {
		$sql .= "and rc.add_date >= :date_from ";
		$parameters['date_from'] = $date_from;
	}
	if (!empty($date_to)) {
		$sql .= "and rc.add_date <= :date_to ";
		$parameters['date_to'] = $date_to . ' 23:59:59';
	}
	if (!empty($filter_status)) {
		$sql .= "and rc.status = :status ";
		$parameters['status'] = $filter_status;
	}

	$sql .= "order by rc.add_date desc ";
	$database = new database;
	$commissions = $database->select($sql, $parameters, 'all');
	if (!is_array($commissions)) { $commissions = []; }

//get summary
	$summary_uuid = $reseller_uuid ?? '';
	if (!empty($summary_uuid)) {
		$summary = $reseller_obj->get_commission_summary($summary_uuid, !empty($date_from) ? $date_from : null, !empty($date_to) ? $date_to : null);
	} else {
		//admin total summary
		$sql = "select ";
		$sql .= "coalesce(sum(case when status = 'pending' then amount else 0 end), 0) as total_pending, ";
		$sql .= "coalesce(sum(case when status = 'paid' then amount else 0 end), 0) as total_paid, ";
		$sql .= "coalesce(sum(amount), 0) as total_earnings ";
		$sql .= "from v_reseller_commissions where 1=1 ";
		$database = new database;
		$summary = $database->select($sql, null, 'row');
		if (!is_array($summary)) { $summary = ['total_pending' => 0, 'total_paid' => 0, 'total_earnings' => 0]; }
	}

//get resellers for filter
	if ($is_admin) {
		$all_resellers = $reseller_obj->get_all_resellers();
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-reseller_commissions'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-reseller_commissions']."</b></div>\n";
	echo "	<div class='actions'>\n";
	if (!$is_admin && isset($reseller_uuid)) {
		echo "	<form method='post' style='display: inline;'>\n";
		echo "		<input type='hidden' name='request_payout' value='true'>\n";
		echo "		<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
		echo button::create(['type'=>'submit','label'=>$text['button-request_payout'],'icon'=>'dollar-sign']);
		echo "	</form>\n";
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<div class='card'>\n";
	echo "	<p>".$text['description-reseller_commissions']."</p>\n";
	echo "</div>\n";

	//summary cards
	echo "<div style='display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;'>\n";
	echo "	<div class='card' style='flex: 1; min-width: 200px; padding: 20px; text-align: center;'>\n";
	echo "		<div style='font-size: 2em; font-weight: bold;'>".number_format((float)$summary['total_earnings'], 2)."</div>\n";
	echo "		<div>".$text['label-total_earnings']."</div>\n";
	echo "	</div>\n";
	echo "	<div class='card' style='flex: 1; min-width: 200px; padding: 20px; text-align: center;'>\n";
	echo "		<div style='font-size: 2em; font-weight: bold;'>".number_format((float)$summary['total_pending'], 2)."</div>\n";
	echo "		<div>".$text['label-total_pending']."</div>\n";
	echo "	</div>\n";
	echo "	<div class='card' style='flex: 1; min-width: 200px; padding: 20px; text-align: center;'>\n";
	echo "		<div style='font-size: 2em; font-weight: bold;'>".number_format((float)$summary['total_paid'], 2)."</div>\n";
	echo "		<div>".$text['label-total_paid']."</div>\n";
	echo "	</div>\n";
	echo "</div>\n";

	//filters
	echo "<div style='margin-bottom: 15px;'>\n";
	echo "	<form method='get' style='display: flex; gap: 10px; flex-wrap: wrap; align-items: center;'>\n";
	if ($is_admin) {
		echo "		<select class='formfld' name='reseller_uuid' style='width: 200px;'>\n";
		echo "			<option value=''>".$text['label-filter_reseller']."</option>\n";
		if (is_array($all_resellers)) {
			foreach ($all_resellers as $r) {
				$selected = ($filter_reseller_uuid === $r['reseller_uuid']) ? "selected='selected'" : '';
				echo "			<option value='".escape($r['reseller_uuid'])."' ".$selected.">".escape($r['company_name'])."</option>\n";
			}
		}
		echo "		</select>\n";
	}
	echo "		<input class='formfld' type='date' name='date_from' value='".escape($date_from)."' placeholder='".$text['label-date_from']."'>\n";
	echo "		<input class='formfld' type='date' name='date_to' value='".escape($date_to)."' placeholder='".$text['label-date_to']."'>\n";
	echo "		<select class='formfld' name='status' style='width: 150px;'>\n";
	echo "			<option value=''>".$text['label-filter_status']."</option>\n";
	echo "			<option value='pending' ".($filter_status === 'pending' ? "selected='selected'" : '').">".$text['option-pending']."</option>\n";
	echo "			<option value='paid' ".($filter_status === 'paid' ? "selected='selected'" : '').">Paid</option>\n";
	echo "			<option value='cancelled' ".($filter_status === 'cancelled' ? "selected='selected'" : '').">Cancelled</option>\n";
	echo "		</select>\n";
	echo "		".button::create(['type'=>'submit','label'=>$text['button-search'],'icon'=>'search']);
	echo "	</form>\n";
	echo "</div>\n";

	//commissions table
	echo "<table class='list'>\n";
	echo "	<tr class='list-header'>\n";
	if ($is_admin) {
		echo "		<th>".$text['label-reseller']."</th>\n";
	}
	echo "		<th>".$text['label-domain_name']."</th>\n";
	echo "		<th>".$text['label-amount']."</th>\n";
	echo "		<th>".$text['label-currency']."</th>\n";
	echo "		<th>".$text['label-commission_status']."</th>\n";
	echo "		<th>".$text['label-paid_date']."</th>\n";
	echo "		<th>".$text['label-date']."</th>\n";
	echo "	</tr>\n";

	if (sizeof($commissions) > 0) {
		foreach ($commissions as $row) {
			echo "	<tr class='list-row'>\n";
			if ($is_admin) {
				echo "		<td>".escape($row['reseller_name'] ?? '')."</td>\n";
			}
			echo "		<td>".escape($row['domain_name'] ?? '')."</td>\n";
			echo "		<td>".number_format((float)$row['amount'], 2)."</td>\n";
			echo "		<td>".escape($row['currency'] ?? '')."</td>\n";
			echo "		<td>".escape($row['status'] ?? '')."</td>\n";
			echo "		<td>".escape($row['paid_date'] ?? '')."</td>\n";
			echo "		<td>".escape($row['add_date'] ?? '')."</td>\n";
			echo "	</tr>\n";
		}
	} else {
		$colspan = $is_admin ? 7 : 6;
		echo "	<tr><td colspan='".$colspan."'>".$text['label-no_records_found']."</td></tr>\n";
	}
	echo "</table>\n";

//include the footer
	require_once "resources/footer.php";

?>
