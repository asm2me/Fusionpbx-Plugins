<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (permission_exists('domain_wizard_add') || permission_exists('domain_wizard_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set the action (add or update)
	if (is_uuid($_REQUEST['id'])) {
		$action = 'update';
		$domain_wizard_template_uuid = $_REQUEST['id'];
	}
	else {
		$action = 'add';
	}

//get http post variables and save to the database
	if (count($_POST) > 0 && strlen($_POST['persistformvar']) == 0) {

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: domain_wizard.php');
				exit;
			}

		//validate the data
			$msg = '';
			if (strlen($_POST['template_name']) == 0) { $msg .= $text['message-required']."<br>\n"; }
			if (!is_uuid($_POST['source_domain_uuid'])) { $msg .= $text['description-source_domain']."<br>\n"; }
			if (strlen($msg) > 0) {
				message::add($msg, 'negative');
				//persist form vars
				$template_name = $_POST['template_name'];
				$source_domain_uuid = $_POST['source_domain_uuid'];
				$default_extensions = $_POST['default_extensions'];
				$default_gateways = $_POST['default_gateways'];
				$default_ivrs = $_POST['default_ivrs'];
				$default_ring_groups = $_POST['default_ring_groups'];
				$description = $_POST['description'];
				$enabled = $_POST['enabled'];
			}
			else {
				//prep the array
					$array['v_domain_wizard_templates'][0]['template_name'] = $_POST['template_name'];
					$array['v_domain_wizard_templates'][0]['source_domain_uuid'] = $_POST['source_domain_uuid'];
					$array['v_domain_wizard_templates'][0]['default_extensions'] = (int)$_POST['default_extensions'];
					$array['v_domain_wizard_templates'][0]['default_gateways'] = (int)$_POST['default_gateways'];
					$array['v_domain_wizard_templates'][0]['default_ivrs'] = (int)$_POST['default_ivrs'];
					$array['v_domain_wizard_templates'][0]['default_ring_groups'] = (int)$_POST['default_ring_groups'];
					$array['v_domain_wizard_templates'][0]['description'] = $_POST['description'];
					$array['v_domain_wizard_templates'][0]['enabled'] = $_POST['enabled'];

					if ($action == 'add') {
						$domain_wizard_template_uuid = uuid();
						$array['v_domain_wizard_templates'][0]['domain_wizard_template_uuid'] = $domain_wizard_template_uuid;
						$array['v_domain_wizard_templates'][0]['add_date'] = 'now()';
						$array['v_domain_wizard_templates'][0]['add_user'] = $_SESSION['user_uuid'];
					}
					else {
						$array['v_domain_wizard_templates'][0]['domain_wizard_template_uuid'] = $domain_wizard_template_uuid;
					}

				//save to the database
					$p = new permissions;
					$p->add('domain_wizard_templates_add', 'temp');
					$p->add('domain_wizard_templates_edit', 'temp');

					$database = new database;
					$database->app_name = 'domain_wizard';
					$database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
					$database->save($array);
					$db_response = $database->message;
					unset($array);

					$p->delete('domain_wizard_templates_add', 'temp');
					$p->delete('domain_wizard_templates_edit', 'temp');

				//check result and show debug info
					if (isset($db_response['code']) && $db_response['code'] == '200') {
						message::add($text['message-template_saved']);
						header('Location: domain_wizard.php');
						exit;
					}
					else {
						$error_detail = isset($db_response['message']) ? $db_response['message'] : 'Unknown error';
						$error_code = isset($db_response['code']) ? $db_response['code'] : 'N/A';
						$debug_info = 'DB Error Code: ' . $error_code . ' | Message: ' . $error_detail;
						if (isset($db_response['error'])) {
							$error_obj = $db_response['error'];
							if (is_array($error_obj)) {
								$debug_info .= ' | Detail: ' . json_encode($error_obj);
							} else {
								$debug_info .= ' | Detail: ' . $error_obj;
							}
						}
						$debug_info .= ' | Full response: ' . json_encode($db_response);
						message::add($debug_info, 'negative');
						//persist form vars for redisplay
						$template_name = $_POST['template_name'];
						$source_domain_uuid = $_POST['source_domain_uuid'];
						$default_extensions = $_POST['default_extensions'];
						$default_gateways = $_POST['default_gateways'];
						$default_ivrs = $_POST['default_ivrs'];
						$default_ring_groups = $_POST['default_ring_groups'];
						$description = $_POST['description'];
						$enabled = $_POST['enabled'];
					}
			}
	}

//pre-populate form for edit
	if ($action == 'update' && count($_POST) == 0) {
		$sql = "select * from v_domain_wizard_templates ";
		$sql .= "where domain_wizard_template_uuid = :domain_wizard_template_uuid ";
		$parameters['domain_wizard_template_uuid'] = $domain_wizard_template_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row)) {
			$template_name = $row['template_name'];
			$source_domain_uuid = $row['source_domain_uuid'];
			$default_extensions = $row['default_extensions'];
			$default_gateways = $row['default_gateways'];
			$default_ivrs = $row['default_ivrs'];
			$default_ring_groups = $row['default_ring_groups'];
			$description = $row['description'];
			$enabled = $row['enabled'];
		}
		unset($sql, $parameters, $row);
	}

//get the domains list
	$sql = "select domain_uuid, domain_name from v_domains order by domain_name asc";
	$database = new database;
	$domains = $database->select($sql, null, 'all');
	unset($sql);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	if ($action == 'add') {
		$document['title'] = $text['title-domain_wizard_add'];
	}
	else {
		$document['title'] = $text['title-domain_wizard_edit'];
	}
	require_once "resources/header.php";

//show the content
	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".($action == 'add' ? $text['title-domain_wizard_add'] : $text['title-domain_wizard_edit'])."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'domain_wizard.php']);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-template_name']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='template_name' maxlength='255' value='".escape($template_name ?? '')."' required='required'>\n";
	echo "	<br />\n";
	echo "	".$text['description-template_name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-source_domain']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='source_domain_uuid' required='required'>\n";
	echo "		<option value=''>".$text['label-select']."</option>\n";
	if (is_array($domains)) {
		foreach ($domains as $domain) {
			$selected = ($domain['domain_uuid'] == ($source_domain_uuid ?? '')) ? "selected='selected'" : '';
			echo "		<option value='".escape($domain['domain_uuid'])."' ".$selected.">".escape($domain['domain_name'])."</option>\n";
		}
	}
	echo "	</select>\n";
	echo "	<br />\n";
	echo "	".$text['description-source_domain']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-default_extensions']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='number' name='default_extensions' min='0' max='999' value='".escape($default_extensions ?? '10')."'>\n";
	echo "	<br />\n";
	echo "	".$text['description-default_extensions']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-default_gateways']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='number' name='default_gateways' min='0' max='99' value='".escape($default_gateways ?? '1')."'>\n";
	echo "	<br />\n";
	echo "	".$text['description-default_gateways']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-default_ivrs']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='number' name='default_ivrs' min='0' max='99' value='".escape($default_ivrs ?? '1')."'>\n";
	echo "	<br />\n";
	echo "	".$text['description-default_ivrs']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-default_ring_groups']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='number' name='default_ring_groups' min='0' max='99' value='".escape($default_ring_groups ?? '1')."'>\n";
	echo "	<br />\n";
	echo "	".$text['description-default_ring_groups']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-description']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<textarea class='formfld' name='description' rows='4'>".escape($description ?? '')."</textarea>\n";
	echo "	<br />\n";
	echo "	".$text['description-description']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-enabled']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='enabled'>\n";
	echo "		<option value='true' ".($enabled == 'true' ? "selected='selected'" : '').">".$text['label-true']."</option>\n";
	echo "		<option value='false' ".($enabled == 'false' ? "selected='selected'" : '').">".$text['label-false']."</option>\n";
	echo "	</select>\n";
	echo "	<br />\n";
	echo "	".$text['description-enabled']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
	echo "<br /><br />\n";

	if ($action == 'update') {
		echo "<input type='hidden' name='domain_wizard_template_uuid' value='".escape($domain_wizard_template_uuid)."'>\n";
	}
	echo "<input type='hidden' name='persistformvar' value=''>\n";

	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
