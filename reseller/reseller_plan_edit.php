<?php

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('reseller_plan_add') && !permission_exists('reseller_plan_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//include the reseller class
	require_once __DIR__ . "/resources/classes/reseller.php";
	$reseller_obj = new reseller;

//determine action
	if (isset($_REQUEST['id']) && is_uuid($_REQUEST['id'])) {
		$action = 'update';
		$reseller_plan_uuid = $_REQUEST['id'];
	} else {
		$action = 'add';
	}

//determine reseller uuid
	$is_admin = permission_exists('reseller_all');
	if ($is_admin && isset($_REQUEST['reseller_uuid']) && is_uuid($_REQUEST['reseller_uuid'])) {
		$reseller_uuid = $_REQUEST['reseller_uuid'];
	} else {
		$profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
		$reseller_uuid = $profile ? $profile['reseller_uuid'] : '';
	}

//process form
	if (count($_POST) > 0 && strlen($_POST['persistformvar']) == 0) {

		$token_check = new token;
		if (!$token_check->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-access_denied'], 'negative');
			header('Location: reseller_plans.php');
			exit;
		}

		$reseller_plan_uuid = $_POST['reseller_plan_uuid'] ?? uuid();
		$plan_reseller_uuid = $_POST['reseller_uuid'] ?? $reseller_uuid;
		$plan_name = $_POST['plan_name'] ?? '';
		$description = $_POST['description'] ?? '';
		$base_plan_uuid = $_POST['base_plan_uuid'] ?? '';
		$markup_amount = $_POST['markup_amount'] ?? '0';
		$markup_type = $_POST['markup_type'] ?? 'fixed';
		$max_extensions = $_POST['max_extensions'] ?? '0';
		$max_gateways = $_POST['max_gateways'] ?? '0';
		$max_ivrs = $_POST['max_ivrs'] ?? '0';
		$features_json = $_POST['features_json'] ?? '[]';
		$enabled = $_POST['enabled'] ?? 'true';

		//validate plan limits against reseller limits
		if (!$is_admin && $profile) {
			if ((int)$max_extensions > (int)$profile['max_total_extensions']) {
				message::add('Plan max extensions cannot exceed your total extension limit.', 'negative');
				//do not exit, allow form to re-display
			}
		}

		$array['v_reseller_plans'][0]['reseller_plan_uuid'] = $reseller_plan_uuid;
		$array['v_reseller_plans'][0]['reseller_uuid'] = $plan_reseller_uuid;
		$array['v_reseller_plans'][0]['plan_name'] = $plan_name;
		$array['v_reseller_plans'][0]['description'] = $description;
		$array['v_reseller_plans'][0]['base_plan_uuid'] = $base_plan_uuid;
		$array['v_reseller_plans'][0]['markup_amount'] = $markup_amount;
		$array['v_reseller_plans'][0]['markup_type'] = $markup_type;
		$array['v_reseller_plans'][0]['max_extensions'] = $max_extensions;
		$array['v_reseller_plans'][0]['max_gateways'] = $max_gateways;
		$array['v_reseller_plans'][0]['max_ivrs'] = $max_ivrs;
		$array['v_reseller_plans'][0]['features_json'] = $features_json;
		$array['v_reseller_plans'][0]['enabled'] = $enabled;

		if ($action === 'add') {
			$array['v_reseller_plans'][0]['add_date'] = 'now()';
			$array['v_reseller_plans'][0]['add_user'] = $_SESSION['user_uuid'];
		}

		$p = new permissions;
		$p->add('v_reseller_plan_add', 'temp');
		$p->add('v_reseller_plan_edit', 'temp');

		$database = new database;
		$database->app_name = 'reseller';
		$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
		$database->save($array);
		unset($array);

		$p->delete('v_reseller_plan_add', 'temp');
		$p->delete('v_reseller_plan_edit', 'temp');

		message::add($text['message-settings_saved']);
		header('Location: reseller_plans.php');
		exit;
	}

//pre-populate for edit
	if ($action === 'update' && isset($reseller_plan_uuid)) {
		$sql = "select * from v_reseller_plans where reseller_plan_uuid = :plan_uuid ";
		$parameters['plan_uuid'] = $reseller_plan_uuid;

		if (!$is_admin) {
			$sql .= "and reseller_uuid = :reseller_uuid ";
			$parameters['reseller_uuid'] = $reseller_uuid;
		}

		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		unset($parameters);

		if (is_array($row)) {
			$plan_name = $row['plan_name'];
			$description = $row['description'];
			$base_plan_uuid = $row['base_plan_uuid'];
			$markup_amount = $row['markup_amount'];
			$markup_type = $row['markup_type'];
			$max_extensions = $row['max_extensions'];
			$max_gateways = $row['max_gateways'];
			$max_ivrs = $row['max_ivrs'];
			$features_json = $row['features_json'];
			$enabled = $row['enabled'];
			$reseller_uuid = $row['reseller_uuid'];
		}
	}

//get base billing plans for dropdown (if billing plugin exists)
	$base_plans = [];
	$sql = "select count(*) as num from information_schema.tables where table_name = 'v_billing_plans' ";
	$database = new database;
	$check = $database->select($sql, null, 'row');
	if (is_array($check) && (int)$check['num'] > 0) {
		$sql = "select plan_uuid, plan_name from v_billing_plans where enabled = 'true' order by plan_name asc ";
		$database = new database;
		$base_plans = $database->select($sql, null, 'all');
		if (!is_array($base_plans)) { $base_plans = []; }
	}

//get resellers list for admin dropdown
	if ($is_admin) {
		$all_resellers = $reseller_obj->get_all_resellers();
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-reseller_plan_edit'];
	require_once "resources/header.php";

//show the content
	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-reseller_plan_edit']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>'arrow-left','link'=>'reseller_plans.php']);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>'check','id'=>'btn_save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<div class='card'>\n";
	echo "	<p>".$text['description-reseller_plan_edit']."</p>\n";
	echo "</div>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	if ($is_admin) {
		echo "<tr>\n";
		echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-reseller']."</td>\n";
		echo "	<td class='vtable' align='left'>\n";
		echo "		<select class='formfld' name='reseller_uuid' required='required'>\n";
		echo "			<option value=''></option>\n";
		if (is_array($all_resellers)) {
			foreach ($all_resellers as $r) {
				$selected = (isset($reseller_uuid) && $r['reseller_uuid'] === $reseller_uuid) ? "selected='selected'" : '';
				echo "			<option value='".escape($r['reseller_uuid'])."' ".$selected.">".escape($r['company_name'])."</option>\n";
			}
		}
		echo "		</select>\n";
		echo "	</td>\n";
		echo "</tr>\n";
	} else {
		echo "<input type='hidden' name='reseller_uuid' value='".escape($reseller_uuid)."'>\n";
	}

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-plan_name']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='plan_name' maxlength='255' value='".escape($plan_name ?? '')."' required='required'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-plan_description']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<textarea class='formfld' name='description' rows='3'>".escape($description ?? '')."</textarea>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-base_plan']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='base_plan_uuid'>\n";
	echo "			<option value=''>-- None --</option>\n";
	foreach ($base_plans as $bp) {
		$selected = (isset($base_plan_uuid) && $bp['plan_uuid'] === $base_plan_uuid) ? "selected='selected'" : '';
		echo "			<option value='".escape($bp['plan_uuid'])."' ".$selected.">".escape($bp['plan_name'])."</option>\n";
	}
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-markup_amount']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='markup_amount' min='0' step='0.01' value='".escape($markup_amount ?? '0')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-markup_type']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='markup_type'>\n";
	echo "			<option value='fixed' ".((isset($markup_type) && $markup_type === 'fixed') ? "selected='selected'" : '').">".$text['option-fixed']."</option>\n";
	echo "			<option value='percentage' ".((isset($markup_type) && $markup_type === 'percentage') ? "selected='selected'" : '').">".$text['option-percentage']."</option>\n";
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-max_extensions']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='max_extensions' min='0' value='".escape($max_extensions ?? '0')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-max_gateways']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='max_gateways' min='0' value='".escape($max_gateways ?? '0')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-max_ivrs']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='number' name='max_ivrs' min='0' value='".escape($max_ivrs ?? '0')."'>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".$text['label-features']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<textarea class='formfld' name='features_json' rows='3' placeholder='[\"voicemail\",\"call_recording\",\"ivr\"]'>".escape($features_json ?? '[]')."</textarea>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>".$text['label-enabled']."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='enabled'>\n";
	echo "			<option value='true' ".(($enabled ?? 'true') === 'true' ? "selected='selected'" : '').">".$text['option-true']."</option>\n";
	echo "			<option value='false' ".(($enabled ?? 'true') === 'false' ? "selected='selected'" : '').">".$text['option-false']."</option>\n";
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	if ($action === 'update') {
		echo "<input type='hidden' name='reseller_plan_uuid' value='".escape($reseller_plan_uuid)."'>\n";
	} else {
		echo "<input type='hidden' name='reseller_plan_uuid' value='".uuid()."'>\n";
	}
	echo "<input type='hidden' name='persistformvar' value=''>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
