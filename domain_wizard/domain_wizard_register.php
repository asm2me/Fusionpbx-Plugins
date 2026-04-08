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

//handle domain check - MODIFIED: Step 2 validation with subdomain restriction
	if ($action === 'check_domain') {
		$domain_name = trim($_GET['domain'] ?? '');
		$subdomain = trim($_GET['subdomain'] ?? '');

		if (empty($domain_name) && empty($subdomain)) {
			echo json_encode(['status' => 'error', 'message' => 'Domain name or subdomain is required']);
			exit;
		}

		//NEW: Force voipat.com subdomains only
		$allowed_base_domain = 'voipat.com';
		$check_domain = $domain_name;
		
		if (!empty($subdomain)) {
			//validate subdomain format
			if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?$/', $subdomain)) {
				echo json_encode(['status' => 'error', 'message' => 'Invalid subdomain format. Only alphanumeric and hyphens allowed.', 'available' => false]);
				exit;
			}
			$check_domain = $subdomain . '.' . $allowed_base_domain;
		}

		//validate if user provided full domain, ensure it's a voipat.com subdomain
		if (!empty($domain_name) && strpos($domain_name, $allowed_base_domain) === false) {
			echo json_encode(['status' => 'error', 'message' => 'Only ' . $allowed_base_domain . ' subdomains are allowed', 'available' => false]);
			exit;
		}

		//check if domain exists in database
		$sql = "select count(*) as count from v_domains where domain_name = :domain_name";
		$parameters['domain_name'] = $check_domain;
		$database = new database;
		$result = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

		$available = ($result == 0);
		
		//NEW: If not available, don't proceed (Step 2 requirement)
		if (!$available) {
			echo json_encode(['status' => 'error', 'message' => 'This domain is already taken. Please choose another.', 'available' => false, 'domain' => $check_domain]);
			exit;
		}

		echo json_encode(['status' => 'success', 'available' => true, 'domain' => $check_domain, 'message' => 'Domain is available']);
		exit;
	}

	//NEW: Handle installation type description request (Step 3)
	if ($action === 'get_installation_types') {
		$installation_types = [
			'company_pbx' => [
				'name' => 'Company PBX',
				'description' => 'Ideal for small to medium businesses. Supports multiple extensions, call routing, voicemail, and basic IVR.',
				'features' => ['Extensions', 'Call Routing', 'Voicemail', 'Ring Groups', 'Basic IVR'],
				'max_extensions' => 100
			],
			'call_center' => [
				'name' => 'Call Center',
				'description' => 'Perfect for customer service operations. Includes agent queues, recording, reporting, and advanced IVR.',
				'features' => ['Agent Queues', 'Call Recording', 'Reports & Analytics', 'Advanced IVR', 'CRM Integration'],
				'max_extensions' => 500
			],
			'autodialer' => [
				'name' => 'Auto Dialer',
				'description' => 'Designed for campaigns and bulk calling. Supports call lists, auto-dialing, and campaign management.',
				'features' => ['Call Lists', 'Auto Dialing', 'Campaign Management', 'Recording', 'Reporting'],
				'max_extensions' => 1000
			]
		];
		echo json_encode(['status' => 'success', 'types' => $installation_types]);
		exit;
	}

	//NEW: Handle device types and trunk services request (Step 6)
	if ($action === 'get_device_types') {
		$device_types = [
			'yealink_gsm' => [
				'name' => 'Yealink GSM/LTE Gateway',
				'vendor' => 'Yealink',
				'description' => 'Professional GSM/LTE gateway for mobile carrier integration',
				'specs' => 'Supports 4 GSM/LTE modules',
				'provision_available' => true
			],
			'dynstar_gsm' => [
				'name' => 'Dynstar GSM/LTE Gateway',
				'vendor' => 'Dynstar',
				'description' => 'High-performance GSM/LTE gateway',
				'specs' => 'Supports 8 GSM/LTE modules',
				'provision_available' => true
			],
			'goip' => [
				'name' => 'DBL GOIP',
				'vendor' => 'DBL',
				'description' => 'VoIP gateway for GSM termination',
				'specs' => 'Supports 4/8/16 channels',
				'provision_available' => true
			],
			'ejoin' => [
				'name' => 'eJoin Gateway',
				'vendor' => 'eJoin',
				'description' => 'All-in-one communication gateway',
				'specs' => 'Multi-protocol support',
				'provision_available' => true
			]
		];

		$trunk_services = [
			'voip_provider' => [
				'name' => 'VoIP Service Provider',
				'description' => 'Traditional VoIP trunk services',
				'requires' => ['username', 'password', 'server']
			],
			'mobile_carrier' => [
				'name' => 'Mobile Carrier Gateway',
				'description' => 'Gateway for mobile network termination',
				'requires' => ['gateway_type', 'channels']
			],
			'local_carrier' => [
				'name' => 'Local Telecom Carrier',
				'description' => 'Traditional telecom carrier trunk',
				'requires' => ['account_number', 'auth_code']
			]
		];

		echo json_encode(['status' => 'success', 'device_types' => $device_types, 'trunk_services' => $trunk_services]);
		exit;
	}

	//NEW: Handle field hints request
	if ($action === 'get_field_hints') {
		$hints = [
			'full_name' => 'Your full legal name as it appears on official documents',
			'email' => 'A valid email address where we can reach you. This will be used for account recovery.',
			'phone' => 'Your contact phone number in international format (e.g., +1234567890)',
			'company' => 'Your company or organization name',
			'subdomain' => 'A unique identifier for your PBX (e.g., "acmecorp" will become acmecorp.voipat.com)',
			'admin_username' => 'The username for your main admin account (minimum 4 characters)',
			'admin_password' => 'A strong password with at least 8 characters, including uppercase, lowercase, and numbers',
			'installation_type' => 'Choose the deployment type that best matches your organization',
			'extension_start' => 'The first extension number to assign (typically 100, 1000, or 2001)',
			'extensions_count' => 'Total number of extensions your plan includes',
			'chart_design' => 'Design your call routing tree using the visual designer. Drag and drop IVR nodes to create your flow.',
			'ivr_menu' => 'Customize IVR options with voice prompts and routing destinations'
		];
		echo json_encode(['status' => 'success', 'hints' => $hints]);
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
		$subdomain = trim($_POST['subdomain'] ?? '');
		$admin_username = trim($_POST['admin_username'] ?? 'admin');
		$admin_password = $_POST['admin_password'] ?? '';
		$plan = trim($_POST['plan'] ?? 'starter');
		
		//NEW: Installation type and configurations
		$installation_type = trim($_POST['installation_type'] ?? 'company_pbx');
		$extension_start = max(100, (int)($_POST['extension_start'] ?? 100));
		$ivr_chart_config = !empty($_POST['ivr_chart_config']) ? json_decode($_POST['ivr_chart_config'], true) : null;
		$device_config = !empty($_POST['device_config']) ? json_decode($_POST['device_config'], true) : null;
		$outbound_config = !empty($_POST['outbound_config']) ? json_decode($_POST['outbound_config'], true) : null;
		$trunk_data = !empty($_POST['trunk_data']) ? json_decode($_POST['trunk_data'], true) : null;

		//construct full domain from subdomain + voipat.com
		$allowed_base_domain = 'voipat.com';
		$domain_name = $subdomain . '.' . $allowed_base_domain;

		//validate required fields
		$errors = [];
		if (empty($full_name)) $errors[] = 'Full name is required';
		if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
		if (empty($subdomain)) $errors[] = 'Subdomain is required';
		if (empty($admin_username)) $errors[] = 'Admin username is required';
		if (empty($admin_password) || strlen($admin_password) < 8) $errors[] = 'Password must be at least 8 characters';
		if (empty($installation_type)) $errors[] = 'Installation type is required';
		
		//NEW: Validate subdomain format
		if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?$/', $subdomain)) {
			$errors[] = 'Invalid subdomain format. Only alphanumeric and hyphens allowed.';
		}

		//validate plan
		$valid_plans = ['starter', 'business', 'enterprise'];
		if (!in_array($plan, $valid_plans)) {
			$errors[] = 'Invalid plan selected';
		}

		//validate installation type
		$valid_types = ['company_pbx', 'call_center', 'autodialer'];
		if (!in_array($installation_type, $valid_types)) {
			$errors[] = 'Invalid installation type';
		}

		if (!empty($errors)) {
			echo json_encode(['status' => 'error', 'message' => implode('. ', $errors)]);
			exit;
		}

		//MODIFIED: Check domain availability - prevent proceeding if exists (Step 2 requirement)
		$sql = "select count(*) as count from v_domains where domain_name = :domain_name";
		$parameters['domain_name'] = $domain_name;
		$database = new database;
		$exists = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

		if ($exists > 0) {
			echo json_encode(['status' => 'error', 'message' => 'Domain name is already in use. Please choose a different subdomain.']);
			exit;
		}

		//NEW: Namecheap subdomain registration - register in Namecheap API
		$namecheap_registered = false;
		if (function_exists('register_namecheap_subdomain')) {
			try {
				$server_ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
				$namecheap_result = register_namecheap_subdomain($subdomain, $server_ip);
				if ($namecheap_result['status'] === 'success') {
					$namecheap_registered = true;
				}
			} catch (Exception $e) {
				//log error but don't fail registration
				error_log("Namecheap registration failed: " . $e->getMessage());
			}
		}

		//plan configuration - MODIFIED: includes installation type settings
		$plan_config = [
			'starter' => [
				'extensions' => 10,
				'gateways' => 1,
				'ivrs' => 2,
				'ring_groups' => 2,
				'devices' => 5,
				'price' => '29.00',
			],
			'business' => [
				'extensions' => 50,
				'gateways' => 5,
				'ivrs' => 10,
				'ring_groups' => 10,
				'devices' => 20,
				'price' => '79.00',
			],
			'enterprise' => [
				'extensions' => 100,
				'gateways' => 10,
				'ivrs' => 20,
				'ring_groups' => 20,
				'devices' => 100,
				'price' => '199.00',
			],
		];

		//installation type specific configurations
		$type_config = [
			'company_pbx' => [
				'features' => ['extensions', 'call_routing', 'voicemail', 'ring_groups', 'basic_ivr'],
				'ivr_limit' => 5,
				'sub_ivr_limit' => 2,
			],
			'call_center' => [
				'features' => ['agent_queues', 'call_recording', 'reporting', 'advanced_ivr', 'crm_integration'],
				'ivr_limit' => 10,
				'sub_ivr_limit' => 5,
			],
			'autodialer' => [
				'features' => ['call_lists', 'auto_dialing', 'campaign_management', 'recording', 'reporting'],
				'ivr_limit' => 3,
				'sub_ivr_limit' => 0,
			]
		];

		$config = $plan_config[$plan];
		$type_opts = $type_config[$installation_type];

		//find demo/template source domain
		$source_domain_uuid = null;

		$sql = "select source_domain_uuid from v_domain_wizard_templates where enabled = :enabled order by add_date desc limit 1";
		$parameters['enabled'] = 'true';
		$database = new database;
		$template_result = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (is_array($template_result) && !empty($template_result['source_domain_uuid'])) {
			$source_domain_uuid = $template_result['source_domain_uuid'];
		}

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

		require_once dirname(__FILE__) . "/resources/classes/domain_wizard.php";

		$wizard = new domain_wizard;

		$user_extensions = min((int)($_POST['extensions_count'] ?? $config['extensions']), $config['extensions']);
		$user_gateways = min((int)($_POST['gateways_count'] ?? $config['gateways']), $config['gateways']);
		$user_ivrs = min((int)($_POST['ivrs_count'] ?? $config['ivrs']), $config['ivrs']);
		$user_ring_groups = min((int)($_POST['ring_groups_count'] ?? $config['ring_groups']), $config['ring_groups']);

		$ivr_configs = [];
		if (!empty($_POST['ivr_configs'])) {
			$ivr_configs = json_decode($_POST['ivr_configs'], true) ?: [];
		}

		//MODIFIED: Enhanced options with new features
		$options = [
			'admin_username' => $admin_username,
			'admin_password' => $admin_password,
			'extensions_count' => $user_extensions,
			'extension_start' => $extension_start,
			'gateways_count' => $user_gateways,
			'ivrs_count' => $user_ivrs,
			'ring_groups_count' => $user_ring_groups,
			'ivr_configs' => $ivr_configs,
			'installation_type' => $installation_type,
			'type_config' => $type_opts,
			'ivr_chart_config' => $ivr_chart_config,
			'device_config' => $device_config,
			'outbound_config' => $outbound_config,
			'trunk_data' => $trunk_data,
		];

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

				if ($domain_uuid) {
					$billing_class = dirname(__DIR__) . "/billing/resources/classes/billing.php";
					if (file_exists($billing_class)) {
						require_once $billing_class;

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

					$p = new permissions;
					$p->add('v_domain_settings_add', 'temp');

					$settings = [];
					$settings[] = ['category' => 'registration', 'subcategory' => 'contact_name', 'value' => $full_name];
					$settings[] = ['category' => 'registration', 'subcategory' => 'contact_email', 'value' => $email];
					$settings[] = ['category' => 'registration', 'subcategory' => 'contact_phone', 'value' => $phone];
					$settings[] = ['category' => 'registration', 'subcategory' => 'company', 'value' => $company];
					$settings[] = ['category' => 'registration', 'subcategory' => 'plan', 'value' => $plan];
					$settings[] = ['category' => 'registration', 'subcategory' => 'installation_type', 'value' => $installation_type];
					$settings[] = ['category' => 'registration', 'subcategory' => 'extension_start', 'value' => (string)$extension_start];
					$settings[] = ['category' => 'registration', 'subcategory' => 'namecheap_registered', 'value' => $namecheap_registered ? 'true' : 'false'];

					if (!empty($ivr_chart_config)) {
						$settings[] = ['category' => 'registration', 'subcategory' => 'ivr_chart_config', 'value' => json_encode($ivr_chart_config)];
					}
					
					if (!empty($device_config)) {
						$settings[] = ['category' => 'registration', 'subcategory' => 'device_config', 'value' => json_encode($device_config)];
					}
					
					if (!empty($outbound_config)) {
						$settings[] = ['category' => 'registration', 'subcategory' => 'outbound_config', 'value' => json_encode($outbound_config)];
					}

					$array = [];
					foreach ($settings as $idx => $setting) {
						$array['v_domain_settings'][$idx]['domain_setting_uuid'] = uuid();
						$array['v_domain_settings'][$idx]['domain_uuid'] = $domain_uuid;
						$array['v_domain_settings'][$idx]['domain_setting_category'] = $setting['category'];
						$array['v_domain_settings'][$idx]['domain_setting_subcategory'] = $setting['subcategory'];
						$array['v_domain_settings'][$idx]['domain_setting_name'] = 'text';
						$array['v_domain_settings'][$idx]['domain_setting_value'] = $setting['value'];
						$array['v_domain_settings'][$idx]['domain_setting_enabled'] = 'true';
					}

					$database = new database;
					$database->app_name = 'domain_wizard';
					$database->app_uuid = '6e1d4a7c-2b8f-4e3d-9c5a-1d7b0e6f3a2c';
					$database->save($array);
					unset($array);

					$p->delete('v_domain_settings_add', 'temp');
				}

				$server_ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());

				echo json_encode([
					'status' => 'success',
					'message' => 'Domain created successfully',
					'domain_uuid' => $domain_uuid,
					'domain_name' => $domain_name,
					'extensions_count' => $config['extensions'],
					'plan' => $plan,
					'installation_type' => $installation_type,
					'server_ip' => $server_ip,
					'login_url' => 'https://' . $domain_name,
					'namecheap_registered' => $namecheap_registered,
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

