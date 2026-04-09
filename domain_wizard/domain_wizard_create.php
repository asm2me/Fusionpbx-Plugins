<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (permission_exists('domain_wizard_add')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get the templates
	$sql = "select * from v_domain_wizard_templates ";
	$sql .= "where enabled = 'true' ";
	$sql .= "order by template_name asc ";
	$database = new database;
	$templates = $database->select($sql, null, 'all');
	unset($sql);

//get the domains list
	$sql = "select domain_uuid, domain_name from v_domains order by domain_name asc";
	$database = new database;
	$domains = $database->select($sql, null, 'all');
	unset($sql);

//pre-select template if passed
	$selected_template_uuid = $_REQUEST['template_uuid'] ?? '';
	$preloaded = [];
	if (is_uuid($selected_template_uuid) && is_array($templates)) {
		foreach ($templates as $tpl) {
			if ($tpl['domain_wizard_template_uuid'] == $selected_template_uuid) {
				$preloaded = $tpl;
				break;
			}
		}
	}

//create token
	$object = new token;
	$token = $object->create('domain_wizard_process.php');

//include the header
	$document['title'] = $text['title-domain_wizard_create'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-domain_wizard_create']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'domain_wizard.php']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

//wizard steps indicator
	echo "<div id='wizard-steps' style='margin: 20px 0; text-align: center;'>\n";
	echo "	<span class='wizard-step active' id='step-indicator-1' style='display: inline-block; padding: 8px 16px; margin: 0 4px; border-radius: 20px; background: #2196F3; color: #fff; font-weight: bold;'>1</span>\n";
	echo "	<span style='display: inline-block; width: 30px; height: 2px; background: #ccc; vertical-align: middle;'></span>\n";
	echo "	<span class='wizard-step' id='step-indicator-2' style='display: inline-block; padding: 8px 16px; margin: 0 4px; border-radius: 20px; background: #e0e0e0; color: #666; font-weight: bold;'>2</span>\n";
	echo "	<span style='display: inline-block; width: 30px; height: 2px; background: #ccc; vertical-align: middle;'></span>\n";
	echo "	<span class='wizard-step' id='step-indicator-3' style='display: inline-block; padding: 8px 16px; margin: 0 4px; border-radius: 20px; background: #e0e0e0; color: #666; font-weight: bold;'>3</span>\n";
	echo "	<span style='display: inline-block; width: 30px; height: 2px; background: #ccc; vertical-align: middle;'></span>\n";
	echo "	<span class='wizard-step' id='step-indicator-4' style='display: inline-block; padding: 8px 16px; margin: 0 4px; border-radius: 20px; background: #e0e0e0; color: #666; font-weight: bold;'>4</span>\n";
	echo "	<span style='display: inline-block; width: 30px; height: 2px; background: #ccc; vertical-align: middle;'></span>\n";
	echo "	<span class='wizard-step' id='step-indicator-5' style='display: inline-block; padding: 8px 16px; margin: 0 4px; border-radius: 20px; background: #e0e0e0; color: #666; font-weight: bold;'>5</span>\n";
	echo "</div>\n";

//begin form
	echo "<form id='wizard_form' method='post' action='domain_wizard_process.php' enctype='multipart/form-data'>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

//step 1: select template or source domain
	echo "<div id='wizard-step-1' class='wizard-panel'>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr><td colspan='2'><h3 style='margin: 10px 0;'>".$text['step-select_template']."</h3></td></tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap' width='30%'>\n";
	echo "	".$text['label-template_name']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='template_uuid' id='template_uuid' onchange='loadTemplateDefaults(this.value);'>\n";
	echo "		<option value=''>".$text['label-select']."</option>\n";
	if (is_array($templates)) {
		foreach ($templates as $tpl) {
			$selected = ($tpl['domain_wizard_template_uuid'] == $selected_template_uuid) ? "selected='selected'" : '';
			echo "		<option value='".escape($tpl['domain_wizard_template_uuid'])."' ".$selected.
				" data-source='".escape($tpl['source_domain_uuid'])."'".
				" data-ext='".escape($tpl['default_extensions'])."'".
				" data-gw='".escape($tpl['default_gateways'])."'".
				" data-ivr='".escape($tpl['default_ivrs'])."'".
				" data-rg='".escape($tpl['default_ring_groups'])."'".
				">".escape($tpl['template_name'])."</option>\n";
		}
	}
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-source_domain']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='source_domain_uuid' id='source_domain_uuid' required='required'>\n";
	echo "		<option value=''>".$text['label-select']."</option>\n";
	if (is_array($domains)) {
		foreach ($domains as $domain) {
			$selected = (isset($preloaded['source_domain_uuid']) && $domain['domain_uuid'] == $preloaded['source_domain_uuid']) ? "selected='selected'" : '';
			echo "		<option value='".escape($domain['domain_uuid'])."' ".$selected.">".escape($domain['domain_name'])."</option>\n";
		}
	}
	echo "	</select>\n";
	echo "	<br />\n";
	echo "	".$text['description-source_domain']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
	echo "<div style='text-align: right; margin-top: 15px;'>\n";
	echo "	<button type='button' class='btn btn-default' onclick='wizardNext(2);'>".$text['button-next']." &raquo;</button>\n";
	echo "</div>\n";
	echo "</div>\n";

//step 2: configure counts
	echo "<div id='wizard-step-2' class='wizard-panel' style='display: none;'>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr><td colspan='2'><h3 style='margin: 10px 0;'>".$text['step-configure']."</h3></td></tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap' width='30%'>\n";
	echo "	".$text['label-extensions_count']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='number' name='extensions_count' id='extensions_count' min='0' max='999' value='".escape($preloaded['default_extensions'] ?? '10')."'>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-extension_start']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='number' name='extension_start' id='extension_start' min='100' max='99999' value='1000'>\n";
	echo "	<br />\n";
	echo "	".$text['description-extension_start']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-gateways_count']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='number' name='gateways_count' id='gateways_count' min='0' max='99' value='".escape($preloaded['default_gateways'] ?? '1')."'>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-ivrs_count']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='number' name='ivrs_count' id='ivrs_count' min='0' max='99' value='".escape($preloaded['default_ivrs'] ?? '1')."'>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-ring_groups_count']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='number' name='ring_groups_count' id='ring_groups_count' min='0' max='99' value='".escape($preloaded['default_ring_groups'] ?? '1')."'>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
	echo "<div style='text-align: right; margin-top: 15px;'>\n";
	echo "	<button type='button' class='btn btn-default' onclick='wizardNext(1);'>&laquo; ".$text['button-previous']."</button>\n";
	echo "	&nbsp;\n";
	echo "	<button type='button' class='btn btn-default' onclick='wizardNext(3);'>".$text['button-next']." &raquo;</button>\n";
	echo "</div>\n";
	echo "</div>\n";

//step 3: upload recordings
	echo "<div id='wizard-step-3' class='wizard-panel' style='display: none;'>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr><td colspan='2'><h3 style='margin: 10px 0;'>".$text['step-recordings']."</h3></td></tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap' width='30%'>\n";
	echo "	".$text['label-upload_recordings']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<div id='drop-zone' style='border: 2px dashed #ccc; border-radius: 8px; padding: 40px 20px; text-align: center; cursor: pointer; background: #fafafa; transition: all 0.3s;'>\n";
	echo "		<span class='fas fa-cloud-upload-alt' style='font-size: 48px; color: #aaa;'></span><br /><br />\n";
	echo "		<span style='font-size: 14px; color: #666;'>".$text['description-upload_recordings']."</span><br /><br />\n";
	echo "		<input type='file' name='recordings[]' id='recordings' multiple accept='.wav,.mp3,.ogg' style='display: none;'>\n";
	echo "		<button type='button' class='btn btn-default' onclick=\"document.getElementById('recordings').click();\">Browse Files</button>\n";
	echo "	</div>\n";
	echo "	<div id='file-list' style='margin-top: 10px;'></div>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
	echo "<div style='text-align: right; margin-top: 15px;'>\n";
	echo "	<button type='button' class='btn btn-default' onclick='wizardNext(2);'>&laquo; ".$text['button-previous']."</button>\n";
	echo "	&nbsp;\n";
	echo "	<button type='button' class='btn btn-default' onclick='wizardNext(4);'>".$text['button-next']." &raquo;</button>\n";
	echo "</div>\n";
	echo "</div>\n";

//step 4: domain settings
	echo "<div id='wizard-step-4' class='wizard-panel' style='display: none;'>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr><td colspan='2'><h3 style='margin: 10px 0;'>".$text['step-domain_settings']."</h3></td></tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap' width='30%'>\n";
	echo "	".$text['label-domain_name']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='domain_name' id='domain_name' maxlength='255' value='' required='required'>\n";
	echo "	<br />\n";
	echo "	".$text['description-domain_name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-admin_username']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='admin_username' id='admin_username' maxlength='255' value='admin' required='required'>\n";
	echo "	<br />\n";
	echo "	".$text['description-admin_username']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-admin_password']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='password' name='admin_password' id='admin_password' maxlength='255' value='' required='required'>\n";
	echo "	<br />\n";
	echo "	".$text['description-admin_password']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
	echo "<div style='text-align: right; margin-top: 15px;'>\n";
	echo "	<button type='button' class='btn btn-default' onclick='wizardNext(3);'>&laquo; ".$text['button-previous']."</button>\n";
	echo "	&nbsp;\n";
	echo "	<button type='button' class='btn btn-default' onclick='prepareReview(); wizardNext(5);'>".$text['button-next']." &raquo;</button>\n";
	echo "</div>\n";
	echo "</div>\n";

//step 5: review and confirm
	echo "<div id='wizard-step-5' class='wizard-panel' style='display: none;'>\n";
	echo "<h3 style='margin: 10px 0;'>".$text['step-review']."</h3>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0' id='review-table'>\n";
	echo "<tr><td class='vncell' width='30%'>".$text['label-source_domain']."</td><td class='vtable' id='review-source'></td></tr>\n";
	echo "<tr><td class='vncell'>".$text['label-domain_name']."</td><td class='vtable' id='review-domain'></td></tr>\n";
	echo "<tr><td class='vncell'>".$text['label-admin_username']."</td><td class='vtable' id='review-username'></td></tr>\n";
	echo "<tr><td class='vncell'>".$text['label-extensions_count']."</td><td class='vtable' id='review-ext'></td></tr>\n";
	echo "<tr><td class='vncell'>".$text['label-extension_start']."</td><td class='vtable' id='review-ext-start'></td></tr>\n";
	echo "<tr><td class='vncell'>".$text['label-gateways_count']."</td><td class='vtable' id='review-gw'></td></tr>\n";
	echo "<tr><td class='vncell'>".$text['label-ivrs_count']."</td><td class='vtable' id='review-ivr'></td></tr>\n";
	echo "<tr><td class='vncell'>".$text['label-ring_groups_count']."</td><td class='vtable' id='review-rg'></td></tr>\n";
	echo "<tr><td class='vncell'>".$text['label-recordings']."</td><td class='vtable' id='review-recordings'></td></tr>\n";
	echo "<tr><td class='vncell' valign='top'>Call Routes Snapshot</td><td class='vtable'><div id='review-call-routes' style='margin-top: 8px; padding: 12px; border: 1px solid #ddd; border-radius: 6px; background: #fff; min-height: 120px;'></div></td></tr>\n";
	echo "</table>\n";

	echo "<div style='text-align: right; margin-top: 15px;'>\n";
	echo "	<button type='button' class='btn btn-default' onclick='wizardNext(4);'>&laquo; ".$text['button-previous']."</button>\n";
	echo "	&nbsp;\n";
	echo "	<button type='submit' class='btn btn-default' id='btn_confirm' style='background: #4CAF50; color: #fff; padding: 8px 24px; font-weight: bold;'>".$text['button-confirm']."</button>\n";
	echo "</div>\n";

//progress area
	echo "<div id='progress-area' style='display: none; margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9;'>\n";
	echo "	<h4>".$text['message-processing']."</h4>\n";
	echo "	<div id='progress-bar-container' style='width: 100%; background: #e0e0e0; border-radius: 4px; overflow: hidden; height: 24px;'>\n";
	echo "		<div id='progress-bar' style='width: 0%; height: 100%; background: #2196F3; transition: width 0.5s; border-radius: 4px;'></div>\n";
	echo "	</div>\n";
	echo "	<div id='progress-log' style='margin-top: 10px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px;'></div>\n";
	echo "</div>\n";

	echo "</div>\n";

	echo "</form>\n";

//javascript for wizard navigation and form handling
	echo "<script>\n";
	echo "var currentStep = 1;\n";
	echo "var totalSteps = 5;\n";
	echo "\n";
	echo "function wizardNext(step) {\n";
	echo "	// validate current step before moving forward\n";
	echo "	if (step > currentStep) {\n";
	echo "		if (!validateStep(currentStep)) return;\n";
	echo "	}\n";
	echo "	// hide all panels\n";
	echo "	for (var i = 1; i <= totalSteps; i++) {\n";
	echo "		document.getElementById('wizard-step-' + i).style.display = 'none';\n";
	echo "		document.getElementById('step-indicator-' + i).style.background = '#e0e0e0';\n";
	echo "		document.getElementById('step-indicator-' + i).style.color = '#666';\n";
	echo "	}\n";
	echo "	// show target panel\n";
	echo "	document.getElementById('wizard-step-' + step).style.display = 'block';\n";
	echo "	// update indicators\n";
	echo "	for (var i = 1; i <= step; i++) {\n";
	echo "		document.getElementById('step-indicator-' + i).style.background = (i === step) ? '#2196F3' : '#4CAF50';\n";
	echo "		document.getElementById('step-indicator-' + i).style.color = '#fff';\n";
	echo "	}\n";
	echo "	currentStep = step;\n";
	echo "}\n";
	echo "\n";
	echo "function validateStep(step) {\n";
	echo "	if (step === 1) {\n";
	echo "		var source = document.getElementById('source_domain_uuid').value;\n";
	echo "		if (!source) {\n";
	echo "			alert('".$text['description-source_domain']."');\n";
	echo "			return false;\n";
	echo "		}\n";
	echo "	}\n";
	echo "	if (step === 4) {\n";
	echo "		var domain = document.getElementById('domain_name').value;\n";
	echo "		var password = document.getElementById('admin_password').value;\n";
	echo "		if (!domain) {\n";
	echo "			alert('".$text['message-invalid_domain']."');\n";
	echo "			return false;\n";
	echo "		}\n";
	echo "		if (!password) {\n";
	echo "			alert('".$text['message-required']."');\n";
	echo "			return false;\n";
	echo "		}\n";
	echo "	}\n";
	echo "	return true;\n";
	echo "}\n";
	echo "\n";
	echo "function loadTemplateDefaults(uuid) {\n";
	echo "	var sel = document.getElementById('template_uuid');\n";
	echo "	var opt = sel.options[sel.selectedIndex];\n";
	echo "	if (uuid && opt) {\n";
	echo "		document.getElementById('source_domain_uuid').value = opt.getAttribute('data-source') || '';\n";
	echo "		document.getElementById('extensions_count').value = opt.getAttribute('data-ext') || '10';\n";
	echo "		document.getElementById('gateways_count').value = opt.getAttribute('data-gw') || '1';\n";
	echo "		document.getElementById('ivrs_count').value = opt.getAttribute('data-ivr') || '1';\n";
	echo "		document.getElementById('ring_groups_count').value = opt.getAttribute('data-rg') || '1';\n";
	echo "	}\n";
	echo "	prepareReview();\n";
	echo "}\n";
	echo "\n";
	echo "function prepareReview() {\n";
	echo "	var sourceSel = document.getElementById('source_domain_uuid');\n";
	echo "	document.getElementById('review-source').textContent = sourceSel.options[sourceSel.selectedIndex].text;\n";
	echo "	document.getElementById('review-domain').textContent = document.getElementById('domain_name').value;\n";
	echo "	document.getElementById('review-username').textContent = document.getElementById('admin_username').value;\n";
	echo "	document.getElementById('review-ext').textContent = document.getElementById('extensions_count').value;\n";
	echo "	document.getElementById('review-ext-start').textContent = document.getElementById('extension_start').value;\n";
	echo "	document.getElementById('review-gw').textContent = document.getElementById('gateways_count').value;\n";
	echo "	document.getElementById('review-ivr').textContent = document.getElementById('ivrs_count').value;\n";
	echo "	document.getElementById('review-rg').textContent = document.getElementById('ring_groups_count').value;\n";
	echo "	var files = document.getElementById('recordings').files;\n";
	echo "	document.getElementById('review-recordings').textContent = files.length + ' file(s)';\n";
	echo "	var routesHtml = '';\n";
	echo "	routesHtml += '<div style=\"font-weight: bold; margin-bottom: 8px;\">Inbound Call Routing</div>';\n";
	echo "	routesHtml += '<div style=\"padding: 10px; background: #f7f7f7; border-radius: 4px; border: 1px solid #e0e0e0;\">';\n";
	echo "	routesHtml += '<div style=\"margin-bottom: 6px;\"><span style=\"display:inline-block;width:10px;height:10px;border-radius:50%;background:#2196F3;margin-right:8px;\"></span>Inbound Calls</div>';\n";
	echo "	routesHtml += '<div style=\"margin-left: 18px; padding-left: 12px; border-left: 2px solid #ccc;\">';\n";
	echo "	routesHtml += '<div>Gateways: ' + document.getElementById('gateways_count').value + '</div>';\n";
	echo "	routesHtml += '<div>IVRs: ' + document.getElementById('ivrs_count').value + '</div>';\n";
	echo "	routesHtml += '<div>Extensions: ' + document.getElementById('extensions_count').value + '</div>';\n";
	echo "	routesHtml += '<div>Ring Groups: ' + document.getElementById('ring_groups_count').value + '</div>';\n";
	echo "	routesHtml += '</div>';\n";
	echo "	routesHtml += '</div>';\n";
	echo "	document.getElementById('review-call-routes').innerHTML = routesHtml;\n";
	echo "}\n";
	echo "\n";
	echo "// drag and drop handling\n";
	echo "var dropZone = document.getElementById('drop-zone');\n";
	echo "var fileInput = document.getElementById('recordings');\n";
	echo "\n";
	echo "dropZone.addEventListener('dragover', function(e) {\n";
	echo "	e.preventDefault();\n";
	echo "	e.stopPropagation();\n";
	echo "	this.style.borderColor = '#2196F3';\n";
	echo "	this.style.background = '#e3f2fd';\n";
	echo "});\n";
	echo "\n";
	echo "dropZone.addEventListener('dragleave', function(e) {\n";
	echo "	e.preventDefault();\n";
	echo "	e.stopPropagation();\n";
	echo "	this.style.borderColor = '#ccc';\n";
	echo "	this.style.background = '#fafafa';\n";
	echo "});\n";
	echo "\n";
	echo "dropZone.addEventListener('drop', function(e) {\n";
	echo "	e.preventDefault();\n";
	echo "	e.stopPropagation();\n";
	echo "	this.style.borderColor = '#ccc';\n";
	echo "	this.style.background = '#fafafa';\n";
	echo "	fileInput.files = e.dataTransfer.files;\n";
	echo "	updateFileList();\n";
	echo "});\n";
	echo "\n";
	echo "dropZone.addEventListener('click', function(e) {\n";
	echo "	if (e.target.tagName !== 'BUTTON') {\n";
	echo "		fileInput.click();\n";
	echo "	}\n";
	echo "});\n";
	echo "\n";
	echo "fileInput.addEventListener('change', function() {\n";
	echo "	updateFileList();\n";
	echo "});\n";
	echo "\n";
	echo "function updateFileList() {\n";
	echo "	var list = document.getElementById('file-list');\n";
	echo "	var files = fileInput.files;\n";
	echo "	var html = '';\n";
	echo "	for (var i = 0; i < files.length; i++) {\n";
	echo "		var size = (files[i].size / 1024).toFixed(1);\n";
	echo "		html += '<div style=\"padding: 4px 8px; margin: 2px 0; background: #f0f0f0; border-radius: 4px;\">';\n";
	echo "		html += '<span class=\"fas fa-file-audio\" style=\"margin-right: 8px;\"></span>';\n";
	echo "		html += files[i].name + ' <small>(' + size + ' KB)</small>';\n";
	echo "		html += '</div>';\n";
	echo "	}\n";
	echo "	list.innerHTML = html;\n";
	echo "}\n";
	echo "\n";
	echo "// form submission with progress\n";
	echo "document.getElementById('wizard_form').addEventListener('submit', function(e) {\n";
	echo "	e.preventDefault();\n";
	echo "	if (!validateStep(4)) return;\n";
	echo "	\n";
	echo "	document.getElementById('btn_confirm').disabled = true;\n";
	echo "	document.getElementById('btn_confirm').textContent = '".$text['message-processing']."';\n";
	echo "	document.getElementById('progress-area').style.display = 'block';\n";
	echo "	\n";
	echo "	var formData = new FormData(this);\n";
	echo "	var xhr = new XMLHttpRequest();\n";
	echo "	xhr.open('POST', 'domain_wizard_process.php', true);\n";
	echo "	\n";
	echo "	xhr.upload.addEventListener('progress', function(e) {\n";
	echo "		if (e.lengthComputable) {\n";
	echo "			var pct = Math.round((e.loaded / e.total) * 50);\n";
	echo "			document.getElementById('progress-bar').style.width = pct + '%';\n";
	echo "		}\n";
	echo "	});\n";
	echo "	\n";
	echo "	xhr.onreadystatechange = function() {\n";
	echo "		if (xhr.readyState === 4) {\n";
	echo "			document.getElementById('progress-bar').style.width = '100%';\n";
	echo "			var logDiv = document.getElementById('progress-log');\n";
	echo "			var rawResponse = xhr.responseText;\n";
	echo "			console.log('Domain Wizard Response (HTTP ' + xhr.status + '):', rawResponse);\n";
	echo "			\n";
	echo "			if (xhr.status !== 200) {\n";
	echo "				document.getElementById('progress-bar').style.background = '#f44336';\n";
	echo "				logDiv.innerHTML = '<div style=\"color: #f44336; font-weight: bold;\">HTTP Error ' + xhr.status + '</div>';\n";
	echo "				logDiv.innerHTML += '<div style=\"font-family: monospace; font-size: 11px; white-space: pre-wrap; max-height: 200px; overflow-y: auto; background: #fff3f3; padding: 8px; margin-top: 8px; border: 1px solid #ffcdd2;\">' + rawResponse.substring(0, 3000) + '</div>';\n";
	echo "				document.getElementById('btn_confirm').disabled = false;\n";
	echo "				document.getElementById('btn_confirm').innerHTML = '".$text['button-confirm']."';\n";
	echo "				return;\n";
	echo "			}\n";
	echo "			\n";
	echo "			try {\n";
	echo "				var result = JSON.parse(rawResponse);\n";
	echo "			} catch(ex) {\n";
	echo "				document.getElementById('progress-bar').style.background = '#f44336';\n";
	echo "				logDiv.innerHTML = '<div style=\"color: #f44336; font-weight: bold;\">Server returned invalid JSON</div>';\n";
	echo "				logDiv.innerHTML += '<div style=\"font-family: monospace; font-size: 11px; white-space: pre-wrap; max-height: 300px; overflow-y: auto; background: #fff3f3; padding: 8px; margin-top: 8px; border: 1px solid #ffcdd2;\">' + rawResponse.substring(0, 3000) + '</div>';\n";
	echo "				document.getElementById('btn_confirm').disabled = false;\n";
	echo "				document.getElementById('btn_confirm').innerHTML = '".$text['button-confirm']."';\n";
	echo "				return;\n";
	echo "			}\n";
	echo "			\n";
	echo "			if (result.status === 'success') {\n";
	echo "				document.getElementById('progress-bar').style.background = '#4CAF50';\n";
	echo "				logDiv.innerHTML = '<div style=\"color: #4CAF50; font-weight: bold;\">".$text['message-domain_created']."</div>';\n";
	echo "			} else {\n";
	echo "				document.getElementById('progress-bar').style.background = '#f44336';\n";
	echo "				logDiv.innerHTML = '<div style=\"color: #f44336; font-weight: bold;\">' + (result.message || '".$text['message-domain_failed']."') + '</div>';\n";
	echo "			}\n";
	echo "			\n";
	echo "			// show log entries\n";
	echo "			if (result.log && result.log.length) {\n";
	echo "				logDiv.innerHTML += '<div style=\"margin-top: 8px; padding: 8px; background: #fafafa; border: 1px solid #ddd; border-radius: 4px; max-height: 250px; overflow-y: auto;\">';\n";
	echo "				for (var i = 0; i < result.log.length; i++) {\n";
	echo "					var color = '#333';\n";
	echo "					var entry = result.log[i];\n";
	echo "					if (entry.indexOf('FAILED') !== -1 || entry.indexOf('FATAL') !== -1) color = '#d32f2f';\n";
	echo "					else if (entry.indexOf('WARNING') !== -1) color = '#f57c00';\n";
	echo "					else if (entry.indexOf('OK:') !== -1) color = '#388e3c';\n";
	echo "					else if (entry.indexOf('INFO:') !== -1) color = '#1976d2';\n";
	echo "					logDiv.innerHTML += '<div style=\"color: ' + color + '; margin-bottom: 3px; font-size: 12px;\">' + entry + '</div>';\n";
	echo "				}\n";
	echo "				logDiv.innerHTML += '</div>';\n";
	echo "			}\n";
	echo "			\n";
	echo "			if (result.status === 'success') {\n";
	echo "				setTimeout(function() { window.location.href = 'domain_wizard.php'; }, 5000);\n";
	echo "			} else {\n";
	echo "				document.getElementById('btn_confirm').disabled = false;\n";
	echo "				document.getElementById('btn_confirm').innerHTML = '".$text['button-confirm']."';\n";
	echo "			}\n";
	echo "		}\n";
	echo "	};\n";
	echo "	\n";
	echo "	xhr.send(formData);\n";
	echo "});\n";
	echo "</script>\n";

//include the footer
	require_once "resources/footer.php";

?>
