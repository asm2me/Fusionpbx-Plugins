<?php

/**
 * Reseller Customer Portal
 *
 * Provides a branded portal page for the reseller's customers.
 * Uses the reseller's branding configuration (logo, company name, support info).
 * Customers can manage their domains and access billing.
 */

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('reseller_portal_view') && !permission_exists('reseller_dashboard')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//include the reseller class
	require_once "resources/classes/reseller.php";
	$reseller_obj = new reseller;

//determine the reseller
	$reseller_uuid = '';
	$reseller_profile = null;

	//if accessed via reseller_uuid parameter (for preview by admin/reseller)
	if (isset($_GET['reseller_uuid']) && is_uuid($_GET['reseller_uuid'])) {
		$reseller_uuid = $_GET['reseller_uuid'];
		$reseller_profile = $reseller_obj->get_profile($reseller_uuid);
	}

	//otherwise try to find the reseller for the current domain
	if (!$reseller_profile) {
		$sql = "select rp.* from v_reseller_profiles rp ";
		$sql .= "inner join v_reseller_domains rd on rp.reseller_uuid = rd.reseller_uuid ";
		$sql .= "where rd.domain_uuid = :domain_uuid ";
		$sql .= "limit 1 ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$database = new database;
		$reseller_profile = $database->select($sql, $parameters, 'row');
		unset($parameters);
	}

	//fallback to own reseller profile
	if (!$reseller_profile) {
		$reseller_profile = $reseller_obj->get_profile_by_user($_SESSION['user_uuid']);
	}

//decode branding
	$branding = [];
	if (is_array($reseller_profile) && !empty($reseller_profile['branding_json'])) {
		$branding = json_decode($reseller_profile['branding_json'], true) ?: [];
	}

	$portal_company = !empty($branding['company_name']) ? $branding['company_name'] : ($reseller_profile['company_name'] ?? 'Service Provider');
	$portal_logo = $branding['logo_url'] ?? '';
	$portal_support_email = $branding['support_email'] ?? ($reseller_profile['contact_email'] ?? '');
	$portal_support_phone = $branding['support_phone'] ?? ($reseller_profile['contact_phone'] ?? '');

//get domain info for the current user
	$sql = "select d.domain_name, d.domain_enabled, rd.status as reseller_status, rd.provisioned_date ";
	$sql .= "from v_domains d ";
	$sql .= "left join v_reseller_domains rd on d.domain_uuid = rd.domain_uuid ";
	$sql .= "where d.domain_uuid = :domain_uuid ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$domain_info = $database->select($sql, $parameters, 'row');
	unset($parameters);

//include the header
	$document['title'] = $text['title-reseller_portal'] . ' - ' . $portal_company;
	require_once "resources/header.php";

//custom portal styles
	echo "<style>\n";
	echo "	.portal-header { text-align: center; padding: 30px 20px; margin-bottom: 20px; }\n";
	echo "	.portal-header img { max-height: 80px; margin-bottom: 15px; }\n";
	echo "	.portal-header h1 { margin: 0; font-size: 1.8em; }\n";
	echo "	.portal-cards { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; }\n";
	echo "	.portal-card { flex: 1; min-width: 280px; padding: 25px; }\n";
	echo "	.portal-card h3 { margin-top: 0; }\n";
	echo "	.portal-support { text-align: center; padding: 20px; margin-top: 20px; }\n";
	echo "</style>\n";

//show the portal content
	echo "<div class='portal-header card'>\n";
	if (!empty($portal_logo)) {
		echo "	<img src='".escape($portal_logo)."' alt='".escape($portal_company)."' /><br/>\n";
	}
	echo "	<h1>".escape($portal_company)."</h1>\n";
	echo "	<p>".$text['description-reseller_portal']."</p>\n";
	echo "</div>\n";

	//domain information card
	echo "<div class='portal-cards'>\n";

	echo "	<div class='portal-card card'>\n";
	echo "		<h3>".$text['label-domain_name']."</h3>\n";
	if (is_array($domain_info)) {
		echo "		<table class='list'>\n";
		echo "			<tr><td><b>".$text['label-domain_name']."</b></td><td>".escape($domain_info['domain_name'] ?? '')."</td></tr>\n";
		echo "			<tr><td><b>".$text['label-status']."</b></td><td>".escape($domain_info['reseller_status'] ?? $domain_info['domain_enabled'] ?? '')."</td></tr>\n";
		echo "			<tr><td><b>".$text['label-provisioned_date']."</b></td><td>".escape($domain_info['provisioned_date'] ?? '')."</td></tr>\n";
		echo "		</table>\n";
	} else {
		echo "		<p>".$text['label-no_records_found']."</p>\n";
	}
	echo "	</div>\n";

	//quick links
	echo "	<div class='portal-card card'>\n";
	echo "		<h3>".$text['label-quick_actions']."</h3>\n";
	echo "		<div style='display: flex; flex-direction: column; gap: 10px;'>\n";

	//link to billing pay page if billing plugin exists
	$billing_link = PROJECT_PATH . '/app/billing/billing_pay.php';
	echo "			<a href='".$billing_link."' class='btn btn-default' style='display: block; text-align: center; padding: 10px;'>".$text['label-subscription']."</a>\n";

	//link to extensions
	$extensions_link = PROJECT_PATH . '/app/extensions/extensions.php';
	echo "			<a href='".$extensions_link."' class='btn btn-default' style='display: block; text-align: center; padding: 10px;'>Extensions</a>\n";

	//link to voicemail
	$voicemail_link = PROJECT_PATH . '/app/voicemails/voicemails.php';
	echo "			<a href='".$voicemail_link."' class='btn btn-default' style='display: block; text-align: center; padding: 10px;'>Voicemail</a>\n";

	echo "		</div>\n";
	echo "	</div>\n";

	echo "</div>\n";

	//support information
	echo "<div class='portal-support card'>\n";
	echo "	<h3>Support</h3>\n";
	if (!empty($portal_support_email)) {
		echo "	<p><b>".$text['label-support_email'].":</b> <a href='mailto:".escape($portal_support_email)."'>".escape($portal_support_email)."</a></p>\n";
	}
	if (!empty($portal_support_phone)) {
		echo "	<p><b>".$text['label-support_phone'].":</b> ".escape($portal_support_phone)."</p>\n";
	}
	echo "</div>\n";

//include the footer
	require_once "resources/footer.php";

?>
