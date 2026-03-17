<?php

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('reseller_activity_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//include the reseller class
	require_once "resources/classes/reseller.php";
	$reseller_obj = new reseller;

//determine scope
	$is_admin = permission_exists('reseller_all');
	$filter_reseller_uuid = $_GET['reseller_uuid'] ?? '';
	$filter_action = $_GET['action_type'] ?? '';
	$filter_domain = $_GET['domain_uuid'] ?? '';
	$date_from = $_GET['date_from'] ?? '';
	$date_to = $_GET['date_to'] ?? '';

//build query
	$sql = "select al.*, d.domain_name, rp.company_name as reseller_name ";
	$sql .= "from v_reseller_activity_log al ";
	$sql .= "left join v_domains d on al.domain_uuid = d.domain_uuid ";
	$sql .= "left join v_reseller_profiles rp on al.reseller_uuid = rp.reseller_uuid ";
	$sql .= "where 1=1 ";
	$parameters = [];

	if (!$is_admin) {
		$profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
		if ($profile) {
			$sql .= "and al.reseller_uuid = :reseller_uuid ";
			$parameters['reseller_uuid'] = $profile['reseller_uuid'];
		} else {
			$sql .= "and 1=0 ";
		}
	} elseif (!empty($filter_reseller_uuid) && is_uuid($filter_reseller_uuid)) {
		$sql .= "and al.reseller_uuid = :reseller_uuid ";
		$parameters['reseller_uuid'] = $filter_reseller_uuid;
	}

	if (!empty($filter_action)) {
		$sql .= "and al.action = :action ";
		$parameters['action'] = $filter_action;
	}

	if (!empty($filter_domain) && is_uuid($filter_domain)) {
		$sql .= "and al.domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $filter_domain;
	}

	if (!empty($date_from)) {
		$sql .= "and al.add_date >= :date_from ";
		$parameters['date_from'] = $date_from;
	}
	if (!empty($date_to)) {
		$sql .= "and al.add_date <= :date_to ";
		$parameters['date_to'] = $date_to . ' 23:59:59';
	}

	$sql .= "order by al.add_date desc ";
	$sql .= "limit 500 ";
	$database = new database;
	$logs = $database->select($sql, $parameters, 'all');
	if (!is_array($logs)) { $logs = []; }

//get resellers and domains for filter
	if ($is_admin) {
		$all_resellers = $reseller_obj->get_all_resellers();
	}

//action types for filter
	$action_types = ['domain_created', 'domain_suspended', 'domain_deleted', 'domain_activated', 'settings_changed', 'user_added', 'api_request', 'payout_requested'];

//include the header
	$document['title'] = $text['title-reseller_activity'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-reseller_activity']."</b></div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<div class='card'>\n";
	echo "	<p>".$text['description-reseller_activity']."</p>\n";
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
	echo "		<select class='formfld' name='action_type' style='width: 180px;'>\n";
	echo "			<option value=''>".$text['label-filter_action']."</option>\n";
	foreach ($action_types as $at) {
		$selected = ($filter_action === $at) ? "selected='selected'" : '';
		$label = $text['option-'.$at] ?? $at;
		echo "			<option value='".escape($at)."' ".$selected.">".$label."</option>\n";
	}
	echo "		</select>\n";
	echo "		<input class='formfld' type='date' name='date_from' value='".escape($date_from)."' placeholder='".$text['label-date_from']."'>\n";
	echo "		<input class='formfld' type='date' name='date_to' value='".escape($date_to)."' placeholder='".$text['label-date_to']."'>\n";
	echo "		".button::create(['type'=>'submit','label'=>$text['button-search'],'icon'=>'search']);
	echo "	</form>\n";
	echo "</div>\n";

	//activity table
	echo "<table class='list'>\n";
	echo "	<tr class='list-header'>\n";
	if ($is_admin) {
		echo "		<th>".$text['label-reseller']."</th>\n";
	}
	echo "		<th>".$text['label-action']."</th>\n";
	echo "		<th>".$text['label-domain_name']."</th>\n";
	echo "		<th>".$text['label-details']."</th>\n";
	echo "		<th>".$text['label-ip_address']."</th>\n";
	echo "		<th>".$text['label-date']."</th>\n";
	echo "	</tr>\n";

	if (sizeof($logs) > 0) {
		foreach ($logs as $row) {
			$action_label = $text['option-'.$row['action']] ?? $row['action'];
			$details = '';
			if (!empty($row['details_json'])) {
				$details_arr = json_decode($row['details_json'], true);
				if (is_array($details_arr)) {
					$details_parts = [];
					foreach ($details_arr as $k => $v) {
						if ($v !== null && $v !== '') {
							$details_parts[] = $k . ': ' . $v;
						}
					}
					$details = implode(', ', $details_parts);
				}
			}

			echo "	<tr class='list-row'>\n";
			if ($is_admin) {
				echo "		<td>".escape($row['reseller_name'] ?? '')."</td>\n";
			}
			echo "		<td>".escape($action_label)."</td>\n";
			echo "		<td>".escape($row['domain_name'] ?? '')."</td>\n";
			echo "		<td>".escape($details)."</td>\n";
			echo "		<td>".escape($row['ip_address'] ?? '')."</td>\n";
			echo "		<td>".escape($row['add_date'] ?? '')."</td>\n";
			echo "	</tr>\n";
		}
	} else {
		$colspan = $is_admin ? 6 : 5;
		echo "	<tr><td colspan='".$colspan."'>".$text['label-no_records_found']."</td></tr>\n";
	}
	echo "</table>\n";

//include the footer
	require_once "resources/footer.php";

?>
