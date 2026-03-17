<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('billing_notice_templates_add') && !permission_exists('billing_notice_templates_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//action add or update
	if (is_uuid($_REQUEST['id'])) {
		$template_uuid = $_REQUEST['id'];
		$action = 'update';
	}
	else {
		$action = 'add';
	}

//handle preview
	if ($_REQUEST['action'] == 'preview' && is_uuid($_REQUEST['id'])) {
		$sql = "select body_html from v_billing_notice_templates where template_uuid = :template_uuid ";
		$parameters['template_uuid'] = $_REQUEST['id'];
		$database = new database;
		$body = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

		//replace variables with sample data
		$sample = [
			'{{domain_name}}' => 'example.com',
			'{{plan_name}}' => 'Professional Plan',
			'{{end_date}}' => date('Y-m-d', strtotime('+30 days')),
			'{{days_remaining}}' => '30',
			'{{amount}}' => '29.99',
			'{{currency}}' => 'USD',
			'{{invoice_number}}' => 'INV-000001',
			'{{payment_url}}' => '#',
			'{{company_name}}' => 'Your Company',
		];
		$body = str_replace(array_keys($sample), array_values($sample), $body);
		echo $body;
		exit;
	}

//get http post variables and save
	if (count($_POST) > 0 && strlen($_POST['template_name']) > 0) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative');
			header('Location: billing_notice_templates.php');
			exit;
		}

		$template_name = $_POST['template_name'];
		$notice_type = $_POST['notice_type'];
		$subject = $_POST['subject'];
		$body_html = $_POST['body_html'];
		$body_text = $_POST['body_text'];
		$enabled = $_POST['enabled'];

		$default_variables = json_encode([
			'{{domain_name}}' => 'Domain name',
			'{{plan_name}}' => 'Subscription plan name',
			'{{end_date}}' => 'Subscription end date',
			'{{days_remaining}}' => 'Days until expiration',
			'{{amount}}' => 'Invoice amount',
			'{{currency}}' => 'Currency code',
			'{{invoice_number}}' => 'Invoice number',
			'{{payment_url}}' => 'Payment URL',
			'{{company_name}}' => 'Company name',
		]);

		$array['v_billing_notice_templates'][0]['template_name'] = $template_name;
		$array['v_billing_notice_templates'][0]['notice_type'] = $notice_type;
		$array['v_billing_notice_templates'][0]['subject'] = $subject;
		$array['v_billing_notice_templates'][0]['body_html'] = $body_html;
		$array['v_billing_notice_templates'][0]['body_text'] = $body_text;
		$array['v_billing_notice_templates'][0]['variables_json'] = $default_variables;
		$array['v_billing_notice_templates'][0]['enabled'] = $enabled;

		if ($action == 'add') {
			$template_uuid = uuid();
			$array['v_billing_notice_templates'][0]['template_uuid'] = $template_uuid;
			$array['v_billing_notice_templates'][0]['add_date'] = date('Y-m-d H:i:s');
		}
		else {
			$array['v_billing_notice_templates'][0]['template_uuid'] = $template_uuid;
		}

		$p = new permissions;
		$p->add('v_billing_notice_templates_'.($action == 'add' ? 'add' : 'edit'), 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array);
		unset($array);
		$p->delete('v_billing_notice_template_'.($action == 'add' ? 'add' : 'edit'), 'temp');

		message::add($text['message-saved']);
		header('Location: billing_notice_templates.php');
		exit;
	}

//pre-populate the form
	if ($action == 'update' && is_uuid($template_uuid)) {
		$sql = "select * from v_billing_notice_templates where template_uuid = :template_uuid ";
		$parameters['template_uuid'] = $template_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row)) {
			$template_name = $row['template_name'];
			$notice_type = $row['notice_type'];
			$subject = $row['subject'];
			$body_html = $row['body_html'];
			$body_text = $row['body_text'];
			$variables_json = $row['variables_json'];
			$enabled = $row['enabled'];
		}
		unset($sql, $parameters, $row);
	}

//set defaults
	if (strlen($enabled) == 0) { $enabled = 'true'; }

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-billing_notice_template_edit'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-billing_notice_template_edit']."</b>\n";
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>'arrow-left','link'=>'billing_notice_templates.php']);
	if ($action == 'update') {
		echo "		<button type='button' class='btn btn-default' onclick='previewTemplate();'><i class='fas fa-eye'></i> ".$text['button-preview']."</button>\n";
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>'check','id'=>'btn_save','form'=>'frm_edit']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-billing_notice_template_edit']."\n";
	echo "<br /><br />\n";

	echo "<form id='frm_edit' method='post'>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top'>".$text['label-template_name']."</td>\n";
	echo "<td width='70%' class='vtable'><input class='formfld' type='text' name='template_name' value='".escape($template_name)."' required='required'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-notice_type']."</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<select class='formfld' name='notice_type'>\n";
	echo "		<option value='expiry_warning_30' ".($notice_type == 'expiry_warning_30' ? "selected='selected'" : "").">".$text['option-expiry_warning_30']."</option>\n";
	echo "		<option value='expiry_warning_7' ".($notice_type == 'expiry_warning_7' ? "selected='selected'" : "").">".$text['option-expiry_warning_7']."</option>\n";
	echo "		<option value='expiry_warning_1' ".($notice_type == 'expiry_warning_1' ? "selected='selected'" : "").">".$text['option-expiry_warning_1']."</option>\n";
	echo "		<option value='expired' ".($notice_type == 'expired' ? "selected='selected'" : "").">".$text['option-expired_notice']."</option>\n";
	echo "		<option value='suspended' ".($notice_type == 'suspended' ? "selected='selected'" : "").">".$text['option-suspended_notice']."</option>\n";
	echo "		<option value='payment_failed' ".($notice_type == 'payment_failed' ? "selected='selected'" : "").">".$text['option-payment_failed']."</option>\n";
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-subject']."</td>\n";
	echo "<td class='vtable'><input class='formfld' type='text' name='subject' value='".escape($subject)."' required='required'></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-body_html']."</td>\n";
	echo "<td class='vtable'><textarea class='formfld' name='body_html' rows='12' style='font-family:monospace;'>".escape($body_html)."</textarea></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-body_text']."</td>\n";
	echo "<td class='vtable'><textarea class='formfld' name='body_text' rows='8' style='font-family:monospace;'>".escape($body_text)."</textarea></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top'>".$text['label-enabled']."</td>\n";
	echo "<td class='vtable'>\n";
	echo "	<select class='formfld' name='enabled'>\n";
	echo "		<option value='true' ".($enabled == 'true' ? "selected='selected'" : "").">".$text['label-true']."</option>\n";
	echo "		<option value='false' ".($enabled == 'false' ? "selected='selected'" : "").">".$text['label-false']."</option>\n";
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	//available variables reference
	echo "<tr>\n";
	echo "<td class='vncell' valign='top'>".$text['label-variables']."</td>\n";
	echo "<td class='vtable'>\n";
	echo "<div style='background:#f5f5f5;padding:10px;border-radius:4px;font-family:monospace;font-size:12px;'>\n";
	$vars = [
		'{{domain_name}}' => 'Domain name',
		'{{plan_name}}' => 'Subscription plan name',
		'{{end_date}}' => 'Subscription end date',
		'{{days_remaining}}' => 'Days until expiration',
		'{{amount}}' => 'Invoice amount',
		'{{currency}}' => 'Currency code',
		'{{invoice_number}}' => 'Invoice number',
		'{{payment_url}}' => 'Payment URL',
		'{{company_name}}' => 'Company name',
	];
	foreach ($vars as $var => $desc) {
		echo "<code>".$var."</code> - ".$desc."<br>\n";
	}
	echo "</div>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
	echo "</form>\n";

//preview modal
	echo "<div id='preview-modal' style='display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;'>\n";
	echo "	<div style='max-width:700px;margin:50px auto;background:#fff;border-radius:8px;padding:20px;max-height:80vh;overflow-y:auto;'>\n";
	echo "		<div style='display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;'>\n";
	echo "			<h3 style='margin:0;'>".$text['button-preview']."</h3>\n";
	echo "			<button type='button' onclick='document.getElementById(\"preview-modal\").style.display=\"none\";' style='border:none;background:none;font-size:24px;cursor:pointer;'>&times;</button>\n";
	echo "		</div>\n";
	echo "		<div id='preview-content'></div>\n";
	echo "	</div>\n";
	echo "</div>\n";

//preview javascript
	if ($action == 'update') {
		echo "<script>\n";
		echo "function previewTemplate() {\n";
		echo "	fetch('billing_notice_template_edit.php?action=preview&id=".escape($template_uuid)."')\n";
		echo "		.then(response => response.text())\n";
		echo "		.then(html => {\n";
		echo "			document.getElementById('preview-content').innerHTML = html;\n";
		echo "			document.getElementById('preview-modal').style.display = 'block';\n";
		echo "		});\n";
		echo "}\n";
		echo "</script>\n";
	}

//include the footer
	require_once "resources/footer.php";

?>
