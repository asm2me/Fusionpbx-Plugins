<?php

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_notice_template_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//handle delete
	if ($_REQUEST['action'] == 'delete' && permission_exists('billing_notice_template_delete')) {
		$token_obj = new token;
		if (!$token_obj->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative');
			header('Location: billing_notice_templates.php');
			exit;
		}
		$template_uuid = $_REQUEST['id'];
		if (is_uuid($template_uuid)) {
			$array['billing_notice_templates'][0]['template_uuid'] = $template_uuid;
			$p = new permissions;
			$p->add('billing_notice_template_delete', 'temp');
			$database = new database;
			$database->app_name = 'billing';
			$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
			$database->delete($array);
			unset($array);
			$p->delete('billing_notice_template_delete', 'temp');
			message::add($text['message-deleted']);
		}
		header('Location: billing_notice_templates.php');
		exit;
	}

//get templates
	$sql = "select * from v_billing_notice_templates order by notice_type asc ";
	$database = new database;
	$templates = $database->select($sql, null, 'all');
	unset($sql);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-billing_notice_templates'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_notice_templates']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('billing_notice_template_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>'plus','link'=>'billing_notice_template_edit.php']);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_notice_templates']."\n";
	echo "<br /><br />\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	echo "	<th>".$text['label-template_name']."</th>\n";
	echo "	<th>".$text['label-notice_type']."</th>\n";
	echo "	<th>".$text['label-subject']."</th>\n";
	echo "	<th>".$text['label-enabled']."</th>\n";
	if (permission_exists('billing_notice_template_edit') || permission_exists('billing_notice_template_delete')) {
		echo "	<th class='action-button'>&nbsp;</th>\n";
	}
	echo "</tr>\n";

	if (is_array($templates) && count($templates) > 0) {
		foreach ($templates as $row) {
			$type_labels = [
				'expiry_warning_30' => '30-Day Warning',
				'expiry_warning_7' => '7-Day Warning',
				'expiry_warning_1' => '1-Day Warning',
				'expired' => 'Expired',
				'suspended' => 'Suspended',
				'payment_failed' => 'Payment Failed',
			];
			echo "<tr class='list-row'>\n";
			if (permission_exists('billing_notice_template_edit')) {
				echo "	<td><a href='billing_notice_template_edit.php?id=".urlencode($row['template_uuid'])."'>".escape($row['template_name'])."</a></td>\n";
			}
			else {
				echo "	<td>".escape($row['template_name'])."</td>\n";
			}
			echo "	<td>".escape($type_labels[$row['notice_type']] ?? $row['notice_type'])."</td>\n";
			echo "	<td>".escape($row['subject'])."</td>\n";
			echo "	<td>".escape($row['enabled'])."</td>\n";
			if (permission_exists('billing_notice_template_edit') || permission_exists('billing_notice_template_delete')) {
				echo "	<td class='action-button'>\n";
				if (permission_exists('billing_notice_template_edit')) {
					echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>'pencil-alt','link'=>'billing_notice_template_edit.php?id='.urlencode($row['template_uuid'])]);
				}
				if (permission_exists('billing_notice_template_delete')) {
					echo button::create(['type'=>'button','title'=>$text['button-delete'],'icon'=>'trash','link'=>'billing_notice_templates.php?action=delete&id='.urlencode($row['template_uuid']).'&token='.$token,'onclick'=>"return confirm('".$text['confirm-delete']."');"]);
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
