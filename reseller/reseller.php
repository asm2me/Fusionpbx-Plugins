<?php

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('reseller_dashboard') && !permission_exists('reseller_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//include the reseller class
	require_once "resources/classes/reseller.php";
	$reseller_obj = new reseller;

//determine if admin or reseller view
	$show_all = (isset($_GET['show']) && $_GET['show'] === 'all' && permission_exists('reseller_all'));

//get data based on role
	if ($show_all) {
		//admin view: list all resellers with stats
		$resellers = $reseller_obj->get_all_resellers();
		$stats = $reseller_obj->get_admin_stats();
	} else {
		//reseller view: get own profile and stats
		$reseller_profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
		if ($reseller_profile) {
			$reseller_uuid = $reseller_profile['reseller_uuid'];
			$domains = $reseller_obj->get_domains($reseller_uuid);
			$commission_summary = $reseller_obj->get_commission_summary($reseller_uuid);
			$domains_remaining = $reseller_obj->get_remaining_quota($reseller_uuid, 'domains');
			$extensions_remaining = $reseller_obj->get_remaining_quota($reseller_uuid, 'extensions');
		}
	}

//include the header
	$document['title'] = $text['title-reseller_dashboard'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>";
	if ($show_all) {
		echo "<b>".$text['title-reseller_manage']."</b>";
	} else {
		echo "<b>".$text['title-reseller_dashboard']."</b>";
	}
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	if ($show_all && permission_exists('reseller_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>'plus','id'=>'btn_add','link'=>'reseller_edit.php']);
	}
	if (!$show_all && isset($reseller_profile)) {
		echo button::create(['type'=>'button','label'=>$text['button-create_domain'],'icon'=>'plus','id'=>'btn_create','link'=>'reseller_domain_create.php']);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if ($show_all) {
		//description
		echo "<div class='card'>\n";
		echo "	<p>".$text['description-reseller_manage']."</p>\n";
		echo "</div>\n";

		//admin stats cards
		echo "<div style='display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;'>\n";
		echo "	<div class='card' style='flex: 1; min-width: 200px; padding: 20px; text-align: center;'>\n";
		echo "		<div style='font-size: 2em; font-weight: bold;'>".(int)$stats['total_resellers']."</div>\n";
		echo "		<div>".$text['label-total_resellers']."</div>\n";
		echo "	</div>\n";
		echo "	<div class='card' style='flex: 1; min-width: 200px; padding: 20px; text-align: center;'>\n";
		echo "		<div style='font-size: 2em; font-weight: bold;'>".(int)$stats['active_resellers']."</div>\n";
		echo "		<div>".$text['option-active']." ".$text['label-total_resellers']."</div>\n";
		echo "	</div>\n";
		echo "	<div class='card' style='flex: 1; min-width: 200px; padding: 20px; text-align: center;'>\n";
		echo "		<div style='font-size: 2em; font-weight: bold;'>".(int)$stats['total_domains']."</div>\n";
		echo "		<div>".$text['label-total_domains']."</div>\n";
		echo "	</div>\n";
		echo "	<div class='card' style='flex: 1; min-width: 200px; padding: 20px; text-align: center;'>\n";
		echo "		<div style='font-size: 2em; font-weight: bold;'>".(int)$stats['active_domains']."</div>\n";
		echo "		<div>".$text['label-active_domains']."</div>\n";
		echo "	</div>\n";
		echo "</div>\n";

		//reseller list
		echo "<table class='list'>\n";
		echo "	<tr class='list-header'>\n";
		echo "		<th>".$text['label-company_name']."</th>\n";
		echo "		<th>".$text['label-contact_name']."</th>\n";
		echo "		<th>".$text['label-contact_email']."</th>\n";
		echo "		<th>".$text['label-status']."</th>\n";
		echo "		<th>".$text['label-total_domains']."</th>\n";
		echo "		<th>".$text['label-max_domains']."</th>\n";
		echo "		<th>".$text['label-commission_rate']."</th>\n";
		echo "		<th class='action-button'>".$text['label-enabled']."</th>\n";
		echo "	</tr>\n";

		if (is_array($resellers) && sizeof($resellers) > 0) {
			foreach ($resellers as $row) {
				$edit_link = "reseller_edit.php?id=".urlencode($row['reseller_uuid']);
				echo "	<tr class='list-row' href='".$edit_link."'>\n";
				echo "		<td><a href='".$edit_link."'>".escape($row['company_name'])."</a></td>\n";
				echo "		<td>".escape($row['contact_name'])."</td>\n";
				echo "		<td>".escape($row['contact_email'])."</td>\n";
				echo "		<td>".escape($row['status'])."</td>\n";
				echo "		<td>".(int)$row['domain_count']."</td>\n";
				echo "		<td>".(int)$row['max_domains']."</td>\n";
				echo "		<td>".escape($row['commission_rate'])."%</td>\n";
				echo "		<td>".escape($row['enabled'])."</td>\n";
				echo "	</tr>\n";
			}
		} else {
			echo "	<tr><td colspan='8'>".$text['label-no_records_found']."</td></tr>\n";
		}
		echo "</table>\n";

	} else {
		//reseller dashboard view
		if (!isset($reseller_profile) || !$reseller_profile) {
			echo "<div class='card'>\n";
			echo "	<p>".$text['message-not_found']."</p>\n";
			echo "</div>\n";
		} else {
			echo "<div class='card'>\n";
			echo "	<p>".$text['description-reseller_dashboard']."</p>\n";
			echo "</div>\n";

			//stats cards
			$domains_count = is_array($domains) ? count($domains) : 0;
			$active_domains = 0;
			if (is_array($domains)) {
				foreach ($domains as $d) {
					if ($d['status'] === 'active') { $active_domains++; }
				}
			}

			echo "<div style='display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;'>\n";

			echo "	<div class='card' style='flex: 1; min-width: 200px; padding: 20px; text-align: center;'>\n";
			echo "		<div style='font-size: 2em; font-weight: bold;'>".$domains_count."</div>\n";
			echo "		<div>".$text['label-total_domains']."</div>\n";
			echo "	</div>\n";

			echo "	<div class='card' style='flex: 1; min-width: 200px; padding: 20px; text-align: center;'>\n";
			echo "		<div style='font-size: 2em; font-weight: bold;'>".$active_domains."</div>\n";
			echo "		<div>".$text['label-active_domains']."</div>\n";
			echo "	</div>\n";

			echo "	<div class='card' style='flex: 1; min-width: 200px; padding: 20px; text-align: center;'>\n";
			echo "		<div style='font-size: 2em; font-weight: bold;'>".(int)$domains_remaining."</div>\n";
			echo "		<div>".$text['label-domains_remaining']."</div>\n";
			echo "	</div>\n";

			echo "	<div class='card' style='flex: 1; min-width: 200px; padding: 20px; text-align: center;'>\n";
			echo "		<div style='font-size: 2em; font-weight: bold;'>".(int)$extensions_remaining."</div>\n";
			echo "		<div>".$text['label-extensions_remaining']."</div>\n";
			echo "	</div>\n";

			echo "</div>\n";

			//commission summary
			if (is_array($commission_summary)) {
				echo "<div class='card' style='margin-bottom: 20px; padding: 20px;'>\n";
				echo "	<h3>".$text['title-reseller_commissions']."</h3>\n";
				echo "	<table class='list' style='margin-top: 10px;'>\n";
				echo "		<tr><td><b>".$text['label-total_earnings']."</b></td><td>".number_format((float)$commission_summary['total_earnings'], 2)."</td></tr>\n";
				echo "		<tr><td><b>".$text['label-total_pending']."</b></td><td>".number_format((float)$commission_summary['total_pending'], 2)."</td></tr>\n";
				echo "		<tr><td><b>".$text['label-total_paid']."</b></td><td>".number_format((float)$commission_summary['total_paid'], 2)."</td></tr>\n";
				echo "	</table>\n";
				echo "</div>\n";
			}

			//quick actions
			echo "<div class='card' style='margin-bottom: 20px; padding: 20px;'>\n";
			echo "	<h3>".$text['label-quick_actions']."</h3>\n";
			echo "	<div style='display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;'>\n";
			echo button::create(['type'=>'button','label'=>$text['button-create_domain'],'icon'=>'plus','link'=>'reseller_domain_create.php']);
			echo button::create(['type'=>'button','label'=>$text['title-reseller_domains'],'icon'=>'globe','link'=>'reseller_domains.php']);
			echo button::create(['type'=>'button','label'=>$text['title-reseller_plans'],'icon'=>'list','link'=>'reseller_plans.php']);
			echo button::create(['type'=>'button','label'=>$text['title-reseller_commissions'],'icon'=>'dollar-sign','link'=>'reseller_commissions.php']);
			echo button::create(['type'=>'button','label'=>$text['title-reseller_settings'],'icon'=>'settings','link'=>'reseller_settings.php']);
			echo "	</div>\n";
			echo "</div>\n";

			//recent domains
			echo "<div class='card' style='padding: 20px;'>\n";
			echo "	<h3>".$text['title-reseller_domains']."</h3>\n";
			echo "	<table class='list' style='margin-top: 10px;'>\n";
			echo "		<tr class='list-header'>\n";
			echo "			<th>".$text['label-domain_name']."</th>\n";
			echo "			<th>".$text['label-status']."</th>\n";
			echo "			<th>".$text['label-provisioned_date']."</th>\n";
			echo "		</tr>\n";
			if (is_array($domains) && sizeof($domains) > 0) {
				$count = 0;
				foreach ($domains as $d) {
					if ($count >= 10) { break; }
					echo "		<tr class='list-row'>\n";
					echo "			<td>".escape($d['domain_name'])."</td>\n";
					echo "			<td>".escape($d['status'])."</td>\n";
					echo "			<td>".escape($d['provisioned_date'])."</td>\n";
					echo "		</tr>\n";
					$count++;
				}
			} else {
				echo "		<tr><td colspan='3'>".$text['label-no_records_found']."</td></tr>\n";
			}
			echo "	</table>\n";
			echo "</div>\n";
		}
	}

//include the footer
	require_once "resources/footer.php";

?>
