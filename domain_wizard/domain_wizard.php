<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (permission_exists('domain_wizard_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//handle delete action
	if (is_array($_POST['templates']) && permission_exists('domain_wizard_delete')) {
		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: domain_wizard.php');
				exit;
			}

		$records = $_POST['templates'];
		if (is_array($records) && @sizeof($records) != 0) {
			//build the delete array
				$x = 0;
				foreach ($records as $record) {
					if (is_uuid($record['uuid'])) {
						$array['v_domain_wizard_templates'][$x]['domain_wizard_template_uuid'] = $record['uuid'];
						$x++;
					}
				}

			//delete the records
				if (is_array($array) && @sizeof($array) != 0) {
					$database = new database;
					$database->app_name = 'domain_wizard';
					$database->app_uuid = '6e1d4a7c-2b8f-4e3d-9c5a-1d7b0e6f3a2c';
					$database->delete($array);
					unset($array);

					message::add($text['message-template_deleted']);
				}
		}

		header('Location: domain_wizard.php');
		exit;
	}

//get the templates
	$sql = "select * from v_domain_wizard_templates ";
	$sql .= "order by template_name asc ";
	$database = new database;
	$templates = $database->select($sql, null, 'all');
	unset($sql);

//get domain names for display
	$sql = "select domain_uuid, domain_name from v_domains order by domain_name asc";
	$database = new database;
	$domains = $database->select($sql, null, 'all');
	$domain_lookup = [];
	if (is_array($domains)) {
		foreach ($domains as $domain) {
			$domain_lookup[$domain['domain_uuid']] = $domain['domain_name'];
		}
	}
	unset($sql, $domains);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-domain_wizard'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-domain_wizard_templates']."</b></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('domain_wizard_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$_SESSION['theme']['button_icon_add'],'id'=>'btn_add','link'=>'domain_wizard_edit.php']);
	}
	if (permission_exists('domain_wizard_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-create'],'icon'=>'magic','id'=>'btn_create','style'=>'margin-left: 15px;','link'=>'domain_wizard_create.php']);
	}
	if (permission_exists('domain_wizard_delete') && is_array($templates) && @sizeof($templates) > 0) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'id'=>'btn_delete','name'=>'btn_delete','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

//delete modal
	if (permission_exists('domain_wizard_delete') && is_array($templates) && @sizeof($templates) > 0) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-delete'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close('modal-delete');"])]);
	}

	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	if (permission_exists('domain_wizard_delete')) {
		echo "	<th class='checkbox'>\n";
		echo "		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick=\"list_all_toggle('checkbox_all');\" ".(is_array($templates) && @sizeof($templates) > 0 ?: "style='visibility: hidden;'").">\n";
		echo "	</th>\n";
	}
	echo "	<th>".$text['label-template_name']."</th>\n";
	echo "	<th>".$text['label-source_domain']."</th>\n";
	echo "	<th class='center'>".$text['label-default_extensions']."</th>\n";
	echo "	<th class='center'>".$text['label-default_gateways']."</th>\n";
	echo "	<th class='center'>".$text['label-default_ivrs']."</th>\n";
	echo "	<th class='center'>".$text['label-default_ring_groups']."</th>\n";
	echo "	<th>".$text['label-enabled']."</th>\n";
	echo "	<th class='hide-sm-dn'>".$text['label-description']."</th>\n";
	echo "	<th class='list-action-column'>&nbsp;</th>\n";
	echo "</tr>\n";

	if (is_array($templates) && @sizeof($templates) > 0) {
		$x = 0;
		foreach ($templates as $row) {
			if (permission_exists('domain_wizard_edit')) {
				$list_row_url = "domain_wizard_edit.php?id=".urlencode($row['domain_wizard_template_uuid']);
			}
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			if (permission_exists('domain_wizard_delete')) {
				echo "	<td class='checkbox'>\n";
				echo "		<input type='checkbox' name='templates[".$x."][uuid]' id='checkbox_".$x."' value='".escape($row['domain_wizard_template_uuid'])."' onclick=\"if (!this.checked) { document.getElementById('checkbox_all').checked = false; }\">\n";
				echo "	</td>\n";
			}
			echo "	<td><a href='".$list_row_url."' class='link_default'>".escape($row['template_name'])."</a></td>\n";
			echo "	<td>".escape($domain_lookup[$row['source_domain_uuid']] ?? '')."</td>\n";
			echo "	<td class='center'>".escape($row['default_extensions'])."</td>\n";
			echo "	<td class='center'>".escape($row['default_gateways'])."</td>\n";
			echo "	<td class='center'>".escape($row['default_ivrs'])."</td>\n";
			echo "	<td class='center'>".escape($row['default_ring_groups'])."</td>\n";
			echo "	<td>".escape($row['enabled'])."</td>\n";
			echo "	<td class='description overflow hide-sm-dn'>".escape($row['description'])."</td>\n";
			echo "	<td class='list-action-column'>\n";
			if (permission_exists('domain_wizard_add')) {
				echo "		<a href='domain_wizard_create.php?template_uuid=".urlencode($row['domain_wizard_template_uuid'])."' title='".$text['button-use_template']."'><span class='fas fa-magic fa-fw btn-list-action'></span></a>\n";
			}
			echo "	</td>\n";
			echo "</tr>\n";
			$x++;
		}
		unset($templates);
	}
	else {
		echo "<tr><td colspan='10' class='center no-wrap'>".$text['message-no_templates']."</td></tr>\n";
	}

	echo "</table>\n";
	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
