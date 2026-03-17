<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_plan_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//handle delete
	if ($_REQUEST['action'] == 'delete' && permission_exists('billing_plan_delete')) {
		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative');
			header('Location: billing_plans.php');
			exit;
		}

		$plan_uuid = $_REQUEST['id'];
		if (is_uuid($plan_uuid)) {
			$array['v_billing_plans'][0]['plan_uuid'] = $plan_uuid;
			$p = new permissions;
			$p->add('billing_plan_delete', 'temp');
			$database = new database;
			$database->app_name = 'billing';
			$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
			$database->delete($array);
			unset($array);
			$p->delete('billing_plan_delete', 'temp');
			message::add($text['message-deleted']);
		}
		header('Location: billing_plans.php');
		exit;
	}

//get plans
	$sql = "select * from v_billing_plans order by plan_name asc ";
	$database = new database;
	$plans = $database->select($sql, null, 'all');
	unset($sql);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-billing_plans'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_plans']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('billing_plan_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>'plus','link'=>'billing_plan_edit.php']);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_plans']."\n";
	echo "<br /><br />\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "	<th>".$text['label-plan_name']."</th>\n";
	echo "	<th>".$text['label-plan_price']."</th>\n";
	echo "	<th>".$text['label-currency']."</th>\n";
	echo "	<th>".$text['label-billing_cycle']."</th>\n";
	echo "	<th>".$text['label-max_extensions']."</th>\n";
	echo "	<th>".$text['label-max_gateways']."</th>\n";
	echo "	<th>".$text['label-max_ivrs']."</th>\n";
	echo "	<th>".$text['label-max_ring_groups']."</th>\n";
	echo "	<th>".$text['label-enabled']."</th>\n";
	if (permission_exists('billing_plan_edit') || permission_exists('billing_plan_delete')) {
		echo "	<th class='action-button'>&nbsp;</th>\n";
	}
	echo "</tr>\n";

	if (is_array($plans) && count($plans) > 0) {
		foreach ($plans as $row) {
			echo "<tr class='list-row'>\n";
			if (permission_exists('billing_plan_edit')) {
				echo "	<td><a href='billing_plan_edit.php?id=".urlencode($row['plan_uuid'])."'>".escape($row['plan_name'])."</a></td>\n";
			}
			else {
				echo "	<td>".escape($row['plan_name'])."</td>\n";
			}
			echo "	<td>".number_format($row['price'], 2)."</td>\n";
			echo "	<td>".escape($row['currency'])."</td>\n";
			echo "	<td>".escape(ucfirst($row['billing_cycle']))."</td>\n";
			echo "	<td>".escape($row['max_extensions'])."</td>\n";
			echo "	<td>".escape($row['max_gateways'])."</td>\n";
			echo "	<td>".escape($row['max_ivrs'])."</td>\n";
			echo "	<td>".escape($row['max_ring_groups'])."</td>\n";
			echo "	<td>".escape($row['enabled'])."</td>\n";
			if (permission_exists('billing_plan_edit') || permission_exists('billing_plan_delete')) {
				echo "	<td class='action-button'>\n";
				if (permission_exists('billing_plan_edit')) {
					echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>'pencil-alt','link'=>'billing_plan_edit.php?id='.urlencode($row['plan_uuid'])]);
				}
				if (permission_exists('billing_plan_delete')) {
					echo button::create(['type'=>'button','title'=>$text['button-delete'],'icon'=>'trash','link'=>'billing_plans.php?action=delete&id='.urlencode($row['plan_uuid']).'&token='.$token,'onclick'=>"return confirm('".$text['confirm-delete']."');"]);
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
