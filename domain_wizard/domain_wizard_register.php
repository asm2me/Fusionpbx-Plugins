<?php

/*
 * domain_wizard_register.php
 *
 * Public registration endpoint for the VOIP@ Cloud website.
 * This page does NOT require authentication - it's called by the
 * public website registration form.
 *
 * Supports:
 *   GET  ?action=check_domain&domain=xxx  - Check domain availability
 *   POST action=register                   - Register a new domain
 */

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";

//establish a superadmin session context for database operations
	if (!isset($_SESSION['user_uuid'])) {
		//find the superadmin user (first user in superadmin group on the default domain)
		$sql = "select u.user_uuid, u.domain_uuid from v_users u ";
		$sql .= "inner join v_user_groups ug on u.user_uuid = ug.user_uuid ";
		$sql .= "inner join v_groups g on ug.group_uuid = g.group_uuid ";
		$sql .= "where g.group_name = 'superadmin' ";
		$sql .= "and u.user_enabled = :enabled ";
		$sql .= "limit 1 ";
		$parameters['enabled'] = 'true';
		$database = new database;
		$superadmin = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (is_array($superadmin)) {
			$_SESSION['user_uuid'] = $superadmin['user_uuid'];
			$_SESSION['domain_uuid'] = $superadmin['domain_uuid'];

			//load superadmin groups into session for permission checks
			$sql = "select g.group_name, g.group_uuid from v_groups g ";
			$sql .= "inner join v_user_groups ug on g.group_uuid = ug.group_uuid ";
			$sql .= "where ug.user_uuid = :user_uuid ";
			$parameters['user_uuid'] = $superadmin['user_uuid'];
			$database = new database;
			$groups = $database->select($sql, $parameters, 'all');
			unset($sql, $parameters);

			if (is_array($groups)) {
				$_SESSION['groups'] = [];
				foreach ($groups as $row) {
					$_SESSION['groups'][] = $row;
				}
			}

			//load permissions for the groups
			if (is_array($groups)) {
				$group_uuids = array_column($groups, 'group_uuid');
				if (!empty($group_uuids)) {
					$placeholders = [];
					$params = [];
					foreach ($group_uuids as $i => $gu) {
						$placeholders[] = ':gu_' . $i;
						$params['gu_' . $i] = $gu;
					}
					$sql = "select permission_name from v_group_permissions ";
					$sql .= "where group_uuid in (" . implode(',', $placeholders) . ") ";
					$sql .= "and permission_assigned = :assigned ";
					$params['assigned'] = 'true';
					$database = new database;
					$perms = $database->select($sql, $params, 'all');
					unset($sql, $params);

					if (is_array($perms)) {
						$_SESSION['permissions'] = [];
						foreach ($perms as $perm) {
							$_SESSION['permissions'][$perm['permission_name']] = true;
						}
					}
				}
			}
		}
	}

//set CORS headers for the website
	$allowed_origins = ['https://www.voipat.com', 'https://voipat.com', 'http://localhost', 'http://127.0.0.1'];
	$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
	if (in_array($origin, $allowed_origins)) {
		header("Access-Control-Allow-Origin: $origin");
		header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type");
	}

//handle preflight
	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
		http_response_code(204);
		exit;
	}

	header('Content-Type: application/json');

//rate limiting - simple file-based
	$rate_limit_dir = '/tmp/voipat_rate_limit';
	if (!is_dir($rate_limit_dir)) {
		mkdir($rate_limit_dir, 0755, true);
	}

	$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	$rate_file = $rate_limit_dir . '/' . md5($client_ip);
	$rate_limit = 10; // max registrations per hour
	$rate_window = 3600;

	if (file_exists($rate_file)) {
		$rate_data = json_decode(file_get_contents($rate_file), true);
		if ($rate_data && $rate_data['timestamp'] > time() - $rate_window) {
			if ($rate_data['count'] >= $rate_limit) {
				echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please try again later.']);
				exit;
			}
		} else {
			$rate_data = ['count' => 0, 'timestamp' => time()];
		}
	} else {
		$rate_data = ['count' => 0, 'timestamp' => time()];
	}

//get the action
	$action = $_REQUEST['action'] ?? '';

//handle domain check
	if ($action === 'check_domain') {
		$domain_name = trim($_GET['domain'] ?? '');

		if (empty($domain_name)) {
			echo json_encode(['status' => 'error', 'message' => 'Domain name is required']);
			exit;
		}

		//validate domain format
		if (!preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $domain_name)) {
			echo json_encode(['status' => 'error', 'message' => 'Invalid domain format', 'available' => false]);
			exit;
		}

		//check if domain exists
		$sql = "select count(*) as count from v_domains where domain_name = :domain_name";
		$parameters['domain_name'] = $domain_name;
		$database = new database;
		$result = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

		$available = ($result == 0);
		echo json_encode(['status' => 'success', 'available' => $available, 'domain' => $domain_name]);
		exit;
	}

//handle registration
	if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {

		//update rate limit
		$rate_data['count']++;
		file_put_contents($rate_file, json_encode($rate_data));

		//collect and sanitize inputs
		$full_name = trim($_POST['full_name'] ?? '');
		$email = trim($_POST['email'] ?? '');
		$phone = trim($_POST['phone'] ?? '');
		$company = trim($_POST['company'] ?? '');
		$domain_name = trim($_POST['domain_name'] ?? '');
		$admin_username = trim($_POST['admin_username'] ?? 'admin');
		$admin_password = $_POST['admin_password'] ?? '';
		$plan = trim($_POST['plan'] ?? 'starter');

		//validate required fields
		$errors = [];
		if (empty($full_name)) $errors[] = 'Full name is required';
		if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
		if (empty($domain_name)) $errors[] = 'Domain name is required';
		if (empty($admin_username)) $errors[] = 'Admin username is required';
		if (empty($admin_password) || strlen($admin_password) < 8) $errors[] = 'Password must be at least 8 characters';

		//validate domain format
		if (!preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $domain_name)) {
			$errors[] = 'Invalid domain name format';
		}

		//validate plan
		$valid_plans = ['starter', 'business', 'enterprise'];
		if (!in_array($plan, $valid_plans)) {
			$errors[] = 'Invalid plan selected';
		}

		if (!empty($errors)) {
			echo json_encode(['status' => 'error', 'message' => implode('. ', $errors)]);
			exit;
		}

		//check domain availability
		$sql = "select count(*) as count from v_domains where domain_name = :domain_name";
		$parameters['domain_name'] = $domain_name;
		$database = new database;
		$exists = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

		if ($exists > 0) {
			echo json_encode(['status' => 'error', 'message' => 'Domain name is already in use']);
			exit;
		}

		//plan configuration
		$plan_config = [
			'starter' => [
				'extensions' => 10,
				'gateways' => 1,
				'ivrs' => 2,
				'ring_groups' => 2,
				'price' => '29.00',
			],
			'business' => [
				'extensions' => 50,
				'gateways' => 5,
				'ivrs' => 10,
				'ring_groups' => 10,
				'price' => '79.00',
			],
			'enterprise' => [
				'extensions' => 100,
				'gateways' => 10,
				'ivrs' => 20,
				'ring_groups' => 20,
				'price' => '199.00',
			],
		];

		$config = $plan_config[$plan];

		//find demo/template source domain
		//first try to find a template, then fall back to the first available domain
		$source_domain_uuid = null;

		//try domain_wizard_templates first
		$sql = "select source_domain_uuid from v_domain_wizard_templates where enabled = :enabled order by add_date desc limit 1";
		$parameters['enabled'] = 'true';
		$database = new database;
		$template_result = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (is_array($template_result) && !empty($template_result['source_domain_uuid'])) {
			$source_domain_uuid = $template_result['source_domain_uuid'];
		}

		//fall back to finding a demo domain or the first domain
		if (empty($source_domain_uuid)) {
			$sql = "select domain_uuid from v_domains where domain_name like :demo_name limit 1";
			$parameters['demo_name'] = '%demo%';
			$database = new database;
			$demo_result = $database->select($sql, $parameters, 'row');
			unset($sql, $parameters);

			if (is_array($demo_result) && !empty($demo_result['domain_uuid'])) {
				$source_domain_uuid = $demo_result['domain_uuid'];
			}
		}

		//last resort: use the first domain
		if (empty($source_domain_uuid)) {
			$sql = "select domain_uuid from v_domains order by domain_name asc limit 1";
			$database = new database;
			$first_result = $database->select($sql, null, 'row');
			unset($sql);

			if (is_array($first_result) && !empty($first_result['domain_uuid'])) {
				$source_domain_uuid = $first_result['domain_uuid'];
			}
		}

		if (empty($source_domain_uuid)) {
			echo json_encode(['status' => 'error', 'message' => 'No source domain available for provisioning. Please contact support.']);
			exit;
		}

		//create the domain using domain_wizard class
		require_once dirname(__FILE__) . "/resources/classes/domain_wizard.php";

		$wizard = new domain_wizard;

		//get user-provided counts, capped by plan max
		$user_extensions = min((int)($_POST['extensions_count'] ?? $config['extensions']), $config['extensions']);
		$user_extension_start = max(100, (int)($_POST['extension_start'] ?? 100));
		$user_gateways = min((int)($_POST['gateways_count'] ?? $config['gateways']), $config['gateways']);
		$user_ivrs = min((int)($_POST['ivrs_count'] ?? $config['ivrs']), $config['ivrs']);
		$user_ring_groups = min((int)($_POST['ring_groups_count'] ?? $config['ring_groups']), $config['ring_groups']);

		//parse IVR and gateway configs from JSON
		$ivr_configs = [];
		if (!empty($_POST['ivr_configs'])) {
			$ivr_configs = json_decode($_POST['ivr_configs'], true) ?: [];
		}

		$gateway_config = null;
		if (!empty($_POST['gateway'])) {
			$gateway_config = json_decode($_POST['gateway'], true);
		}

		$options = [
			'admin_username' => $admin_username,
			'admin_password' => $admin_password,
			'extensions_count' => $user_extensions,
			'extension_start' => $user_extension_start,
			'gateways_count' => $user_gateways,
			'ivrs_count' => $user_ivrs,
			'ring_groups_count' => $user_ring_groups,
			'ivr_configs' => $ivr_configs,
			'gateway_config' => $gateway_config,
		];

		//handle IVR recording file uploads
		$ivr_recordings = [];
		foreach ($_FILES as $key => $file) {
			if (strpos($key, 'ivr_recording_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
				$index = (int)str_replace('ivr_recording_', '', $key);
				$ivr_recordings[$index] = $file;
			}
		}
		$options['ivr_recordings'] = $ivr_recordings;

		try {
			$result = $wizard->clone_domain($source_domain_uuid, $domain_name, $options);

			if ($result['status'] === 'success') {
				$domain_uuid = $result['domain_uuid'] ?? null;

				//create a billing subscription if billing plugin is available
				if ($domain_uuid) {
					$billing_class = dirname(__DIR__) . "/billing/resources/classes/billing.php";
					if (file_exists($billing_class)) {
						require_once $billing_class;

						//find or create the billing plan
						$sql = "select billing_plan_uuid from v_billing_plans where plan_name = :plan_name and enabled = :enabled limit 1";
						$parameters['plan_name'] = ucfirst($plan);
						$parameters['enabled'] = 'true';
						$database = new database;
						$plan_row = $database->select($sql, $parameters, 'row');
						unset($sql, $parameters);

						if (is_array($plan_row) && !empty($plan_row['billing_plan_uuid'])) {
							$billing = new billing;
							$billing->create_subscription($domain_uuid, $plan_row['billing_plan_uuid'], [
								'auto_renew' => true,
								'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+14 days')),
							]);
						}
					}

					//store registration metadata as domain settings
					$p = new permissions;
					$p->add('v_domain_settings_add', 'temp');

					$array['v_domain_settings'][0]['domain_setting_uuid'] = uuid();
					$array['v_domain_settings'][0]['domain_uuid'] = $domain_uuid;
					$array['v_domain_settings'][0]['domain_setting_category'] = 'registration';
					$array['v_domain_settings'][0]['domain_setting_subcategory'] = 'contact_name';
					$array['v_domain_settings'][0]['domain_setting_name'] = 'text';
					$array['v_domain_settings'][0]['domain_setting_value'] = $full_name;
					$array['v_domain_settings'][0]['domain_setting_enabled'] = 'true';

					$array['v_domain_settings'][1]['domain_setting_uuid'] = uuid();
					$array['v_domain_settings'][1]['domain_uuid'] = $domain_uuid;
					$array['v_domain_settings'][1]['domain_setting_category'] = 'registration';
					$array['v_domain_settings'][1]['domain_setting_subcategory'] = 'contact_email';
					$array['v_domain_settings'][1]['domain_setting_name'] = 'text';
					$array['v_domain_settings'][1]['domain_setting_value'] = $email;
					$array['v_domain_settings'][1]['domain_setting_enabled'] = 'true';

					$array['v_domain_settings'][2]['domain_setting_uuid'] = uuid();
					$array['v_domain_settings'][2]['domain_uuid'] = $domain_uuid;
					$array['v_domain_settings'][2]['domain_setting_category'] = 'registration';
					$array['v_domain_settings'][2]['domain_setting_subcategory'] = 'contact_phone';
					$array['v_domain_settings'][2]['domain_setting_name'] = 'text';
					$array['v_domain_settings'][2]['domain_setting_value'] = $phone;
					$array['v_domain_settings'][2]['domain_setting_enabled'] = 'true';

					$array['v_domain_settings'][3]['domain_setting_uuid'] = uuid();
					$array['v_domain_settings'][3]['domain_uuid'] = $domain_uuid;
					$array['v_domain_settings'][3]['domain_setting_category'] = 'registration';
					$array['v_domain_settings'][3]['domain_setting_subcategory'] = 'company';
					$array['v_domain_settings'][3]['domain_setting_name'] = 'text';
					$array['v_domain_settings'][3]['domain_setting_value'] = $company;
					$array['v_domain_settings'][3]['domain_setting_enabled'] = 'true';

					$array['v_domain_settings'][4]['domain_setting_uuid'] = uuid();
					$array['v_domain_settings'][4]['domain_uuid'] = $domain_uuid;
					$array['v_domain_settings'][4]['domain_setting_category'] = 'registration';
					$array['v_domain_settings'][4]['domain_setting_subcategory'] = 'plan';
					$array['v_domain_settings'][4]['domain_setting_name'] = 'text';
					$array['v_domain_settings'][4]['domain_setting_value'] = $plan;
					$array['v_domain_settings'][4]['domain_setting_enabled'] = 'true';

					$database = new database;
					$database->app_name = 'domain_wizard';
					$database->app_uuid = '6e1d4a7c-2b8f-4e3d-9c5a-1d7b0e6f3a2c';
					$database->save($array);
					unset($array);

					$p->delete('v_domain_settings_add', 'temp');
				}

				//get server IP for DNS instructions
				$server_ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());

				echo json_encode([
					'status' => 'success',
					'message' => 'Domain created successfully',
					'domain_uuid' => $domain_uuid,
					'domain_name' => $domain_name,
					'extensions_count' => $config['extensions'],
					'plan' => $plan,
					'server_ip' => $server_ip,
					'login_url' => 'https://' . $domain_name,
				]);
			} else {
				echo json_encode([
					'status' => 'error',
					'message' => $result['message'] ?? 'Domain creation failed',
					'details' => implode("\n", $result['log'] ?? []),
				]);
			}
		} catch (Exception $e) {
			echo json_encode([
				'status' => 'error',
				'message' => 'An error occurred during registration',
				'details' => $e->getMessage(),
			]);
		}

		exit;
	}

//invalid action
	echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
