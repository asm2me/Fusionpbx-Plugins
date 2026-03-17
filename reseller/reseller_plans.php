<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('reseller_plans_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//include the reseller class
	require_once __DIR__ . "/resources/classes/reseller.php";
	$reseller_obj = new reseller;

//handle delete action
	if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && is_uuid($_GET['id'])) {
		if (permission_exists('reseller_plans_delete')) {
			$token_obj = new token;
			if ($token_obj->validate($_SERVER['PHP_SELF'])) {
				$sql = "delete from v_reseller_plans where reseller_plan_uuid = :plan_uuid ";
				$parameters['plan_uuid'] = $_GET['id'];

				//if not admin, restrict to own plans
				if (!permission_exists('reseller_all')) {
					$profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
					if ($profile) {
						$sql .= "and reseller_uuid = :reseller_uuid ";
						$parameters['reseller_uuid'] = $profile['reseller_uuid'];
					}
				}

				$database = new database;
				$database->execute($sql, $parameters);
				unset($parameters);
			}
		}
		header('Location: reseller_plans.php');
		exit;
	}

//determine scope
	$is_admin = permission_exists('reseller_all');

	if ($is_admin) {
		$sql = "select rp.*, rpro.company_name as reseller_name ";
		$sql .= "from v_reseller_plans rp ";
		$sql .= "left join v_reseller_profiles rpro on rp.reseller_uuid = rpro.reseller_uuid ";
		$sql .= "order by rpro.company_name, rp.plan_name asc ";
		$database = new database;
		$plans = $database->select($sql, null, 'all');
	} else {
		$profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
		if ($profile) {
			$sql = "select * from v_reseller_plans where reseller_uuid = :reseller_uuid order by plan_name asc ";
			$parameters['reseller_uuid'] = $profile['reseller_uuid'];
			$database = new database;
			$plans = $database->select($sql, $parameters, 'all');
			unset($parameters);
		} else {
			$plans = [];
		}
	}
	if (!is_array($plans)) { $plans = []; }

//create token for delete actions
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-reseller_plans'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-reseller_plans']."</b></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('reseller_plans_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>'plus','link'=>'reseller_plan_edit.php']);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<div class='card'>\n";
	echo "	<p>".$text['description-reseller_plans']."</p>\n";
	echo "</div>\n";

	echo "<table class='list'>\n";
	echo "	<tr class='list-header'>\n";
	echo "		<th>".$text['label-plan_name']."</th>\n";
	if ($is_admin) {
		echo "		<th>".$text['label-reseller']."</th>\n";
	}
	echo "		<th>".$text['label-plan_description']."</th>\n";
	echo "		<th>".$text['label-markup_amount']."</th>\n";
	echo "		<th>".$text['label-markup_type']."</th>\n";
	echo "		<th>".$text['label-max_extensions']."</th>\n";
	echo "		<th>".$text['label-max_gateways']."</th>\n";
	echo "		<th>".$text['label-max_ivrs']."</th>\n";
	echo "		<th>".$text['label-enabled']."</th>\n";
	echo "		<th class='action-button'></th>\n";
	echo "	</tr>\n";

	if (sizeof($plans) > 0) {
		foreach ($plans as $row) {
			$edit_link = "reseller_plan_edit.php?id=".urlencode($row['reseller_plan_uuid']);
			echo "	<tr class='list-row' href='".$edit_link."'>\n";
			echo "		<td><a href='".$edit_link."'>".escape($row['plan_name'])."</a></td>\n";
			if ($is_admin) {
				echo "		<td>".escape($row['reseller_name'] ?? '')."</td>\n";
			}
			echo "		<td>".escape($row['description'] ?? '')."</td>\n";
			echo "		<td>".escape($row['markup_amount'])."</td>\n";
			echo "		<td>".escape($row['markup_type'])."</td>\n";
			echo "		<td>".escape($row['max_extensions'])."</td>\n";
			echo "		<td>".escape($row['max_gateways'])."</td>\n";
			echo "		<td>".escape($row['max_ivrs'])."</td>\n";
			echo "		<td>".escape($row['enabled'])."</td>\n";
			echo "		<td class='action-button'>\n";
			if (permission_exists('reseller_plans_edit')) {
				echo "			<a href='".$edit_link."'>".$text['button-edit']."</a>\n";
			}
			if (permission_exists('reseller_plans_delete')) {
				echo "			| <a href='reseller_plans.php?action=delete&id=".urlencode($row['reseller_plan_uuid'])."&".$token['name']."=".$token['hash']."' onclick=\"return confirm('".$text['message-confirm_delete']."');\">".$text['button-delete']."</a>\n";
			}
			echo "		</td>\n";
			echo "	</tr>\n";
		}
	} else {
		$colspan = $is_admin ? 10 : 9;
		echo "	<tr><td colspan='".$colspan."'>".$text['label-no_records_found']."</td></tr>\n";
	}
	echo "</table>\n";

//include the footer
	require_once "resources/footer.php";

?>
