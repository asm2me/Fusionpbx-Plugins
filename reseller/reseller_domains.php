<?php

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('reseller_domains_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//include the reseller class
	require_once "resources/classes/reseller.php";
	$reseller_obj = new reseller;

//handle actions (suspend, activate, delete)
	if (isset($_GET['action']) && isset($_GET['domain_uuid']) && is_uuid($_GET['domain_uuid'])) {
		//validate token
		$token = new token;
		if ($token->validate($_SERVER['PHP_SELF'])) {
			$action_type = $_GET['action'];
			$target_domain_uuid = $_GET['domain_uuid'];

			//determine reseller UUID
			if (permission_exists('reseller_all') && isset($_GET['reseller_uuid']) && is_uuid($_GET['reseller_uuid'])) {
				$action_reseller_uuid = $_GET['reseller_uuid'];
			} else {
				$profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
				$action_reseller_uuid = $profile ? $profile['reseller_uuid'] : '';
			}

			if (!empty($action_reseller_uuid)) {
				switch ($action_type) {
					case 'suspend':
						$reseller_obj->suspend_domain($action_reseller_uuid, $target_domain_uuid);
						message::add($text['option-domain_suspended']);
						break;
					case 'activate':
						$reseller_obj->activate_domain($action_reseller_uuid, $target_domain_uuid);
						message::add($text['option-domain_activated']);
						break;
					case 'delete':
						if (permission_exists('reseller_domains_delete')) {
							$reseller_obj->delete_domain($action_reseller_uuid, $target_domain_uuid);
							message::add($text['option-domain_deleted']);
						}
						break;
				}
			}
		}
		header('Location: reseller_domains.php');
		exit;
	}

//determine view scope
	$is_admin = permission_exists('reseller_all');
	$filter_reseller_uuid = $_GET['reseller_uuid'] ?? '';

	if ($is_admin && !empty($filter_reseller_uuid) && is_uuid($filter_reseller_uuid)) {
		$reseller_uuid = $filter_reseller_uuid;
		$domains = $reseller_obj->get_domains($reseller_uuid);
	} elseif ($is_admin && empty($filter_reseller_uuid)) {
		//show all reseller domains
		$sql = "select rd.*, d.domain_name, rp.company_name as reseller_name ";
		$sql .= "from v_reseller_domains rd ";
		$sql .= "left join v_domains d on rd.domain_uuid = d.domain_uuid ";
		$sql .= "left join v_reseller_profiles rp on rd.reseller_uuid = rp.reseller_uuid ";
		$sql .= "order by rp.company_name, d.domain_name asc ";
		$database = new database;
		$domains = $database->select($sql, null, 'all');
		if (!is_array($domains)) { $domains = []; }
	} else {
		$profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
		if ($profile) {
			$reseller_uuid = $profile['reseller_uuid'];
			$domains = $reseller_obj->get_domains($reseller_uuid);
		} else {
			$domains = [];
		}
	}

//search filter
	$search = $_GET['search'] ?? '';
	if (!empty($search)) {
		$search_lower = strtolower($search);
		$domains = array_filter($domains, function($d) use ($search_lower) {
			return (
				stripos($d['domain_name'] ?? '', $search_lower) !== false ||
				stripos($d['status'] ?? '', $search_lower) !== false ||
				stripos($d['reseller_name'] ?? '', $search_lower) !== false
			);
		});
	}

//get resellers list for filter dropdown (admin only)
	if ($is_admin) {
		$all_resellers = $reseller_obj->get_all_resellers();
	}

//create token for actions
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-reseller_domains'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-reseller_domains']."</b></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('reseller_domains_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-create_domain'],'icon'=>'plus','link'=>'reseller_domain_create.php']);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<div class='card'>\n";
	echo "	<p>".$text['description-reseller_domains']."</p>\n";
	echo "</div>\n";

	//search and filter bar
	echo "<div style='margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;'>\n";
	echo "	<form method='get'>\n";
	echo "		<input class='formfld' type='text' name='search' placeholder='".$text['button-search']."' value='".escape($search)."' style='width: 250px;'>\n";
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
	echo "		".button::create(['type'=>'submit','label'=>$text['button-search'],'icon'=>'search']);
	echo "	</form>\n";
	echo "</div>\n";

	//domain list table
	echo "<table class='list'>\n";
	echo "	<tr class='list-header'>\n";
	echo "		<th>".$text['label-domain_name']."</th>\n";
	if ($is_admin) {
		echo "		<th>".$text['label-reseller']."</th>\n";
	}
	echo "		<th>".$text['label-status']."</th>\n";
	echo "		<th>".$text['label-provisioned_date']."</th>\n";
	echo "		<th>".$text['label-notes']."</th>\n";
	echo "		<th class='action-button'>".$text['label-quick_actions']."</th>\n";
	echo "	</tr>\n";

	if (is_array($domains) && sizeof($domains) > 0) {
		foreach ($domains as $row) {
			$rd_reseller_uuid = $row['reseller_uuid'] ?? ($reseller_uuid ?? '');
			echo "	<tr class='list-row'>\n";
			echo "		<td>".escape($row['domain_name'] ?? '')."</td>\n";
			if ($is_admin) {
				echo "		<td>".escape($row['reseller_name'] ?? '')."</td>\n";
			}
			echo "		<td>".escape($row['status'] ?? '')."</td>\n";
			echo "		<td>".escape($row['provisioned_date'] ?? '')."</td>\n";
			echo "		<td>".escape($row['notes'] ?? '')."</td>\n";
			echo "		<td class='action-button' style='white-space: nowrap;'>\n";
			if ($row['status'] === 'active') {
				echo "			<a href='reseller_domains.php?action=suspend&domain_uuid=".urlencode($row['domain_uuid'])."&reseller_uuid=".urlencode($rd_reseller_uuid)."&".$token['name']."=".$token['hash']."' onclick=\"return confirm('".$text['message-confirm_suspend']."');\">".$text['button-suspend']."</a>\n";
			} else {
				echo "			<a href='reseller_domains.php?action=activate&domain_uuid=".urlencode($row['domain_uuid'])."&reseller_uuid=".urlencode($rd_reseller_uuid)."&".$token['name']."=".$token['hash']."' onclick=\"return confirm('".$text['message-confirm_activate']."');\">".$text['button-activate']."</a>\n";
			}
			if (permission_exists('reseller_domains_delete')) {
				echo "			| <a href='reseller_domains.php?action=delete&domain_uuid=".urlencode($row['domain_uuid'])."&reseller_uuid=".urlencode($rd_reseller_uuid)."&".$token['name']."=".$token['hash']."' onclick=\"return confirm('".$text['message-confirm_delete']."');\">".$text['button-delete']."</a>\n";
			}
			echo "		</td>\n";
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
