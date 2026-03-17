<?php

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('domain_wizard_add')) {
		//access granted
	}
	else {
		echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
		exit;
	}

//set content type
	header('Content-Type: application/json');

//validate the token
	$token = new token;
	if (!$token->validate('domain_wizard_process.php')) {
		echo json_encode(['status' => 'error', 'message' => 'Invalid token.']);
		exit;
	}

//include the domain wizard class
	require_once "resources/classes/domain_wizard.php";

//get the posted data
	$template_uuid = $_POST['template_uuid'] ?? '';
	$source_domain_uuid = $_POST['source_domain_uuid'] ?? '';
	$domain_name = trim($_POST['domain_name'] ?? '');
	$admin_username = trim($_POST['admin_username'] ?? '');
	$admin_password = $_POST['admin_password'] ?? '';
	$extensions_count = (int)($_POST['extensions_count'] ?? 0);
	$extension_start = (int)($_POST['extension_start'] ?? 1000);
	$gateways_count = (int)($_POST['gateways_count'] ?? 0);
	$ivrs_count = (int)($_POST['ivrs_count'] ?? 0);
	$ring_groups_count = (int)($_POST['ring_groups_count'] ?? 0);

//validate required fields
	$errors = [];
	if (!is_uuid($source_domain_uuid)) {
		$errors[] = 'Invalid source domain.';
	}
	if (strlen($domain_name) == 0) {
		$errors[] = 'Domain name is required.';
	}
	if (strlen($admin_username) == 0) {
		$errors[] = 'Admin username is required.';
	}
	if (strlen($admin_password) == 0) {
		$errors[] = 'Admin password is required.';
	}

//validate domain name format
	if (strlen($domain_name) > 0 && !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\.\-]+[a-zA-Z0-9]$/', $domain_name)) {
		$errors[] = 'Invalid domain name format.';
	}

//check if domain already exists
	if (strlen($domain_name) > 0) {
		$sql = "select count(*) as cnt from v_domains where domain_name = :domain_name";
		$parameters['domain_name'] = $domain_name;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && $row['cnt'] > 0) {
			$errors[] = 'Domain name already exists.';
		}
		unset($sql, $parameters, $row);
	}

	if (count($errors) > 0) {
		echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
		exit;
	}

//build options
	$options = [
		'template_uuid' => $template_uuid,
		'extensions_count' => $extensions_count,
		'extension_start' => $extension_start,
		'gateways_count' => $gateways_count,
		'ivrs_count' => $ivrs_count,
		'ring_groups_count' => $ring_groups_count,
		'admin_username' => $admin_username,
		'admin_password' => $admin_password,
		'recordings' => $_FILES['recordings'] ?? null,
	];

//create the domain wizard instance and clone
	try {
		$wizard = new domain_wizard;
		$result = $wizard->clone_domain($source_domain_uuid, $domain_name, $options);

		if ($result['status'] == 'success') {
			echo json_encode([
				'status' => 'success',
				'message' => 'Domain created successfully.',
				'domain_uuid' => $result['domain_uuid'],
				'log' => $result['log'] ?? [],
			]);
		}
		else {
			echo json_encode([
				'status' => 'error',
				'message' => $result['message'] ?? 'Domain creation failed.',
				'log' => $result['log'] ?? [],
			]);
		}
	}
	catch (Exception $e) {
		//log the error
			$wizard_log = new domain_wizard;
			$wizard_log->log_action([
				'domain_uuid' => null,
				'template_uuid' => $template_uuid,
				'created_by' => $_SESSION['user_uuid'],
				'extensions_count' => $extensions_count,
				'gateways_count' => $gateways_count,
				'ivrs_count' => $ivrs_count,
				'recordings_uploaded' => 0,
				'status' => 'failed',
				'log_detail' => $e->getMessage(),
			]);

		echo json_encode([
			'status' => 'error',
			'message' => 'An error occurred: ' . $e->getMessage(),
		]);
	}

?>
