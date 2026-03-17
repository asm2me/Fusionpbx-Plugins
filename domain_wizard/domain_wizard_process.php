<?php

//set content type early to ensure JSON response
	header('Content-Type: application/json');

//capture any PHP errors/warnings that would break JSON
	ob_start();

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

//check permissions
	if (!permission_exists('domain_wizard_add')) {
		ob_end_clean();
		echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
		exit;
	}

//validate the token
	$token = new token;
	if (!$token->validate('domain_wizard_process.php')) {
		ob_end_clean();
		echo json_encode(['status' => 'error', 'message' => 'Invalid token. Please reload the page and try again.']);
		exit;
	}

//include the domain wizard class
	require_once __DIR__ . "/resources/classes/domain_wizard.php";

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
			$errors[] = 'Domain name "' . $domain_name . '" already exists.';
		}
		unset($sql, $parameters, $row);
	}

	if (count($errors) > 0) {
		ob_end_clean();
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

		//capture any stray output from the clone process
		$stray_output = ob_get_clean();

		if (isset($result['status']) && $result['status'] == 'success') {
			$response = [
				'status' => 'success',
				'message' => 'Domain created successfully.',
				'domain_uuid' => $result['domain_uuid'] ?? null,
				'log' => $result['log'] ?? [],
			];
		} else {
			$response = [
				'status' => 'error',
				'message' => $result['message'] ?? 'Domain creation failed. Check the log below.',
				'log' => $result['log'] ?? [],
			];
		}

		//append stray output to log if any
		if (!empty($stray_output)) {
			$response['log'][] = 'PHP Output: ' . substr(strip_tags($stray_output), 0, 2000);
		}

		echo json_encode($response);
	}
	catch (Exception $e) {
		$stray_output = ob_get_clean();

		$log = ['FAILED: Exception thrown - ' . $e->getMessage()];
		if (!empty($stray_output)) {
			$log[] = 'PHP Output: ' . substr(strip_tags($stray_output), 0, 2000);
		}
		$log[] = 'File: ' . $e->getFile() . ':' . $e->getLine();

		echo json_encode([
			'status' => 'error',
			'message' => $e->getMessage(),
			'log' => $log,
		]);
	}
	catch (\Throwable $e) {
		$stray_output = ob_get_clean();

		$log = ['FATAL: ' . $e->getMessage()];
		if (!empty($stray_output)) {
			$log[] = 'PHP Output: ' . substr(strip_tags($stray_output), 0, 2000);
		}
		$log[] = 'File: ' . $e->getFile() . ':' . $e->getLine();

		echo json_encode([
			'status' => 'error',
			'message' => $e->getMessage(),
			'log' => $log,
		]);
	}

?>
