<?php

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_gateway_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//handle delete
	if ($_REQUEST['action'] == 'delete' && permission_exists('billing_gateway_delete')) {
		$token_obj = new token;
		if (!$token_obj->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative');
			header('Location: billing_gateways.php');
			exit;
		}
		$gateway_uuid = $_REQUEST['id'];
		if (is_uuid($gateway_uuid)) {
			$array['billing_payment_gateways'][0]['gateway_uuid'] = $gateway_uuid;
			$p = new permissions;
			$p->add('billing_gateway_delete', 'temp');
			$database = new database;
			$database->app_name = 'billing';
			$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
			$database->delete($array);
			unset($array);
			$p->delete('billing_gateway_delete', 'temp');
			message::add($text['message-deleted']);
		}
		header('Location: billing_gateways.php');
		exit;
	}

//get gateways
	$sql = "select * from v_billing_payment_gateways order by gateway_name asc ";
	$database = new database;
	$gateways = $database->select($sql, null, 'all');
	unset($sql);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-billing_gateways'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_gateways']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('billing_gateway_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>'plus','link'=>'billing_gateway_edit.php']);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_gateways']."\n";
	echo "<br /><br />\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "	<th>".$text['label-gateway_name']."</th>\n";
	echo "	<th>".$text['label-display_name']."</th>\n";
	echo "	<th>".$text['label-sandbox_mode']."</th>\n";
	echo "	<th>".$text['label-enabled']."</th>\n";
	if (permission_exists('billing_gateway_edit') || permission_exists('billing_gateway_delete')) {
		echo "	<th class='action-button'>&nbsp;</th>\n";
	}
	echo "</tr>\n";

	if (is_array($gateways) && count($gateways) > 0) {
		foreach ($gateways as $row) {
			echo "<tr class='list-row'>\n";
			if (permission_exists('billing_gateway_edit')) {
				echo "	<td><a href='billing_gateway_edit.php?id=".urlencode($row['gateway_uuid'])."'>".escape(ucfirst($row['gateway_name']))."</a></td>\n";
			}
			else {
				echo "	<td>".escape(ucfirst($row['gateway_name']))."</td>\n";
			}
			echo "	<td>".escape($row['display_name'])."</td>\n";
			echo "	<td>".escape($row['sandbox_mode'] == 'true' ? 'Yes' : 'No')."</td>\n";
			echo "	<td>".escape($row['enabled'])."</td>\n";
			if (permission_exists('billing_gateway_edit') || permission_exists('billing_gateway_delete')) {
				echo "	<td class='action-button'>\n";
				if (permission_exists('billing_gateway_edit')) {
					echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>'pencil-alt','link'=>'billing_gateway_edit.php?id='.urlencode($row['gateway_uuid'])]);
				}
				if (permission_exists('billing_gateway_delete')) {
					echo button::create(['type'=>'button','title'=>$text['button-delete'],'icon'=>'trash','link'=>'billing_gateways.php?action=delete&id='.urlencode($row['gateway_uuid']).'&token='.$token,'onclick'=>"return confirm('".$text['confirm-delete']."');"]);
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
