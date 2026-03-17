<?php

/**
 * reseller class
 *
 * Provides reseller management functionality including domain creation,
 * quota management, commission tracking, and activity logging.
 */
if (!class_exists('reseller')) {
	class reseller {

		public $reseller_uuid;
		public $user_uuid;
		public $domain_uuid;

		/**
		 * Get the reseller profile
		 * @param string $reseller_uuid
		 * @return array|false
		 */
		public function get_profile($reseller_uuid) {
			$sql = "select * from v_reseller_profiles ";
			$sql .= "where reseller_uuid = :reseller_uuid ";
			$parameters['reseller_uuid'] = $reseller_uuid;
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			if (is_array($row) && sizeof($row) > 0) {
				return $row;
			}
			return false;
		}

		/**
		 * Get the reseller profile by user UUID
		 * @param string $user_uuid
		 * @return array|false
		 */
		public function get_profile_by_user($user_uuid) {
			$sql = "select * from v_reseller_profiles ";
			$sql .= "where user_uuid = :user_uuid ";
			$parameters['user_uuid'] = $user_uuid;
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			if (is_array($row) && sizeof($row) > 0) {
				return $row;
			}
			return false;
		}

		/**
		 * Get all domains managed by the reseller
		 * @param string $reseller_uuid
		 * @return array
		 */
		public function get_domains($reseller_uuid) {
			$sql = "select rd.*, d.domain_name ";
			$sql .= "from v_reseller_domains as rd ";
			$sql .= "left join v_domains as d on rd.domain_uuid = d.domain_uuid ";
			$sql .= "where rd.reseller_uuid = :reseller_uuid ";
			$sql .= "order by d.domain_name asc ";
			$parameters['reseller_uuid'] = $reseller_uuid;
			$database = new database;
			$result = $database->select($sql, $parameters, 'all');
			if (is_array($result) && sizeof($result) > 0) {
				return $result;
			}
			return [];
		}

		/**
		 * Create a domain for the reseller
		 * @param string $reseller_uuid
		 * @param array $domain_data - keys: domain_name, plan_uuid, num_extensions, num_gateways, admin_username, admin_password
		 * @return array ['success' => bool, 'message' => string, 'domain_uuid' => string|null]
		 */
		public function create_domain($reseller_uuid, $domain_data) {
			//validate reseller status
			$profile = $this->get_profile($reseller_uuid);
			if (!$profile || $profile['status'] !== 'active' || $profile['enabled'] !== 'true') {
				return ['success' => false, 'message' => 'Reseller account is not active.', 'domain_uuid' => null];
			}

			//check domain quota
			$quota_check = $this->check_quota($reseller_uuid, 'domains');
			if (!$quota_check) {
				return ['success' => false, 'message' => 'Domain quota exceeded.', 'domain_uuid' => null];
			}

			//check extension quota
			$remaining_ext = $this->get_remaining_quota($reseller_uuid, 'extensions');
			if (isset($domain_data['num_extensions']) && $domain_data['num_extensions'] > $remaining_ext) {
				return ['success' => false, 'message' => 'Extension quota would be exceeded.', 'domain_uuid' => null];
			}

			//check if domain name already exists
			$sql = "select count(*) as num from v_domains where domain_name = :domain_name ";
			$parameters['domain_name'] = $domain_data['domain_name'];
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			unset($parameters);
			if ($row['num'] > 0) {
				return ['success' => false, 'message' => 'Domain name already exists.', 'domain_uuid' => null];
			}

			//create the domain
			$new_domain_uuid = uuid();
			$array['v_domains'][0]['domain_uuid'] = $new_domain_uuid;
			$array['v_domains'][0]['domain_name'] = $domain_data['domain_name'];
			$array['v_domains'][0]['domain_enabled'] = 'true';

			$p = new permissions;
			$p->add('domain_add', 'temp');

			$database = new database;
			$database->app_name = 'reseller';
			$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
			$database->save($array);
			unset($array);

			$p->delete('domain_add', 'temp');

			//check if domain was created
			if ($database->message['code'] != '200') {
				return ['success' => false, 'message' => 'Failed to create domain in database.', 'domain_uuid' => null];
			}

			//use domain_wizard class if available for advanced cloning
			if (class_exists('domain_wizard')) {
				try {
					$wizard = new domain_wizard;
					//attempt to use wizard for additional setup
				} catch (Exception $e) {
					//wizard not available, continue with basic creation
				}
			}

			//create admin user for the new domain
			if (!empty($domain_data['admin_username']) && !empty($domain_data['admin_password'])) {
				$new_user_uuid = uuid();
				$salt = uuid();
				$password_hash = md5($salt . $domain_data['admin_password']);

				$array['v_users'][0]['user_uuid'] = $new_user_uuid;
				$array['v_users'][0]['domain_uuid'] = $new_domain_uuid;
				$array['v_users'][0]['username'] = $domain_data['admin_username'];
				$array['v_users'][0]['password'] = $password_hash;
				$array['v_users'][0]['salt'] = $salt;
				$array['v_users'][0]['user_enabled'] = 'true';
				$array['v_users'][0]['add_date'] = 'now()';
				$array['v_users'][0]['add_user'] = $_SESSION['user_uuid'] ?? '';

				$p = new permissions;
				$p->add('user_add', 'temp');

				$database = new database;
				$database->app_name = 'reseller';
				$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
				$database->save($array);
				unset($array);

				$p->delete('user_add', 'temp');

				//assign admin group to user
				$group_uuid = '';
				$sql = "select group_uuid from v_groups where group_name = 'admin' limit 1 ";
				$database = new database;
				$row = $database->select($sql, null, 'row');
				if (is_array($row)) {
					$group_uuid = $row['group_uuid'];
				}

				if (!empty($group_uuid)) {
					$array['v_user_groups'][0]['user_group_uuid'] = uuid();
					$array['v_user_groups'][0]['domain_uuid'] = $new_domain_uuid;
					$array['v_user_groups'][0]['group_name'] = 'admin';
					$array['v_user_groups'][0]['group_uuid'] = $group_uuid;
					$array['v_user_groups'][0]['user_uuid'] = $new_user_uuid;

					$p = new permissions;
					$p->add('user_group_add', 'temp');

					$database = new database;
					$database->app_name = 'reseller';
					$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
					$database->save($array);
					unset($array);

					$p->delete('user_group_add', 'temp');
				}
			}

			//set domain limits via default settings
			if (!empty($domain_data['num_extensions'])) {
				$this->set_domain_setting($new_domain_uuid, 'limit', 'extensions', 'numeric', $domain_data['num_extensions']);
			}
			if (!empty($domain_data['num_gateways'])) {
				$this->set_domain_setting($new_domain_uuid, 'limit', 'gateways', 'numeric', $domain_data['num_gateways']);
			}

			//record the reseller domain mapping
			$reseller_domain_uuid = uuid();
			$array['v_reseller_domains'][0]['reseller_domain_uuid'] = $reseller_domain_uuid;
			$array['v_reseller_domains'][0]['reseller_uuid'] = $reseller_uuid;
			$array['v_reseller_domains'][0]['domain_uuid'] = $new_domain_uuid;
			$array['v_reseller_domains'][0]['status'] = 'active';
			$array['v_reseller_domains'][0]['provisioned_date'] = 'now()';
			$array['v_reseller_domains'][0]['add_date'] = 'now()';
			$array['v_reseller_domains'][0]['add_user'] = $_SESSION['user_uuid'] ?? '';

			//link subscription if plan_uuid provided
			if (!empty($domain_data['plan_uuid'])) {
				$subscription_uuid = $this->create_billing_subscription($reseller_uuid, $new_domain_uuid, $domain_data['plan_uuid']);
				if ($subscription_uuid) {
					$array['v_reseller_domains'][0]['subscription_uuid'] = $subscription_uuid;
				}
			}

			$p = new permissions;
			$p->add('reseller_domains_add', 'temp');

			$database = new database;
			$database->app_name = 'reseller';
			$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
			$database->save($array);
			unset($array);

			$p->delete('reseller_domains_add', 'temp');

			//log activity
			$this->log_activity($reseller_uuid, 'domain_created', [
				'domain_uuid' => $new_domain_uuid,
				'domain_name' => $domain_data['domain_name'],
				'plan_uuid' => $domain_data['plan_uuid'] ?? null,
			], $new_domain_uuid);

			return ['success' => true, 'message' => 'Domain created successfully.', 'domain_uuid' => $new_domain_uuid];
		}

		/**
		 * Create a domain directly as superadmin (no reseller context)
		 * @param array $domain_data - keys: domain_name, num_extensions, num_gateways, num_ivrs, num_ring_groups, admin_username, admin_password, source_domain_uuid
		 * @return array ['success' => bool, 'message' => string, 'domain_uuid' => string|null]
		 */
		public function create_domain_direct($domain_data) {
			//check if domain name already exists
			$sql = "select count(*) as num from v_domains where domain_name = :domain_name ";
			$parameters['domain_name'] = $domain_data['domain_name'];
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			unset($parameters);
			if (is_array($row) && (int)$row['num'] > 0) {
				return ['success' => false, 'message' => 'Domain name already exists.', 'domain_uuid' => null];
			}

			//create the domain
			$new_domain_uuid = uuid();
			$array['v_domains'][0]['domain_uuid'] = $new_domain_uuid;
			$array['v_domains'][0]['domain_name'] = $domain_data['domain_name'];
			$array['v_domains'][0]['domain_enabled'] = 'true';

			$p = new permissions;
			$p->add('domain_add', 'temp');

			$database = new database;
			$database->app_name = 'reseller';
			$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
			$database->save($array);
			unset($array);

			$p->delete('domain_add', 'temp');

			if ($database->message['code'] != '200') {
				return ['success' => false, 'message' => 'Failed to create domain in database.', 'domain_uuid' => null];
			}

			//clone from source domain if specified
			if (!empty($domain_data['source_domain_uuid']) && is_uuid($domain_data['source_domain_uuid'])) {
				$this->clone_domain_data($domain_data['source_domain_uuid'], $new_domain_uuid, $domain_data);
			}

			//create admin user for the new domain
			if (!empty($domain_data['admin_username']) && !empty($domain_data['admin_password'])) {
				$this->create_domain_admin_user($new_domain_uuid, $domain_data['admin_username'], $domain_data['admin_password']);
			}

			//set domain limits
			if (!empty($domain_data['num_extensions'])) {
				$this->set_domain_setting($new_domain_uuid, 'limit', 'extensions', 'numeric', $domain_data['num_extensions']);
			}
			if (!empty($domain_data['num_gateways'])) {
				$this->set_domain_setting($new_domain_uuid, 'limit', 'gateways', 'numeric', $domain_data['num_gateways']);
			}
			if (!empty($domain_data['num_ivrs'])) {
				$this->set_domain_setting($new_domain_uuid, 'limit', 'ivrs', 'numeric', $domain_data['num_ivrs']);
			}
			if (!empty($domain_data['num_ring_groups'])) {
				$this->set_domain_setting($new_domain_uuid, 'limit', 'ring_groups', 'numeric', $domain_data['num_ring_groups']);
			}

			return ['success' => true, 'message' => 'Domain created successfully.', 'domain_uuid' => $new_domain_uuid];
		}

		/**
		 * Clone data from a source domain to a new domain
		 * @param string $source_uuid
		 * @param string $target_uuid
		 * @param array $options
		 */
		private function clone_domain_data($source_uuid, $target_uuid, $options = []) {
			$num_extensions = (int) ($options['num_extensions'] ?? 0);
			$num_gateways = (int) ($options['num_gateways'] ?? 0);

			//clone extensions
			if ($num_extensions > 0) {
				$sql = "select * from v_extensions where domain_uuid = :domain_uuid order by extension asc limit :limit ";
				$parameters['domain_uuid'] = $source_uuid;
				$parameters['limit'] = $num_extensions;
				$database = new database;
				$extensions = $database->select($sql, $parameters, 'all');
				unset($parameters);

				if (is_array($extensions)) {
					$ext_num = 1000;
					foreach ($extensions as $idx => $ext) {
						$new_ext_uuid = uuid();
						$array['v_extensions'][$idx]['extension_uuid'] = $new_ext_uuid;
						$array['v_extensions'][$idx]['domain_uuid'] = $target_uuid;
						$array['v_extensions'][$idx]['extension'] = (string)($ext_num + $idx);
						$array['v_extensions'][$idx]['number_alias'] = '';
						$array['v_extensions'][$idx]['password'] = bin2hex(random_bytes(8));
						$array['v_extensions'][$idx]['enabled'] = 'true';
						$array['v_extensions'][$idx]['description'] = 'Extension ' . ($ext_num + $idx);
					}

					$p = new permissions;
					$p->add('extension_add', 'temp');
					$database = new database;
					$database->app_name = 'reseller';
					$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
					$database->save($array);
					unset($array);
					$p->delete('extension_add', 'temp');
				}
			}

			//clone gateways
			if ($num_gateways > 0) {
				$sql = "select * from v_gateways where domain_uuid = :domain_uuid order by gateway asc limit :limit ";
				$parameters['domain_uuid'] = $source_uuid;
				$parameters['limit'] = $num_gateways;
				$database = new database;
				$gateways = $database->select($sql, $parameters, 'all');
				unset($parameters);

				if (is_array($gateways)) {
					foreach ($gateways as $idx => $gw) {
						$new_gw_uuid = uuid();
						$array['v_gateways'][$idx]['gateway_uuid'] = $new_gw_uuid;
						$array['v_gateways'][$idx]['domain_uuid'] = $target_uuid;
						$array['v_gateways'][$idx]['gateway'] = $gw['gateway'] ?? 'gateway_' . ($idx + 1);
						$array['v_gateways'][$idx]['username'] = $gw['username'] ?? '';
						$array['v_gateways'][$idx]['password'] = $gw['password'] ?? '';
						$array['v_gateways'][$idx]['proxy'] = $gw['proxy'] ?? '';
						$array['v_gateways'][$idx]['register'] = $gw['register'] ?? 'false';
						$array['v_gateways'][$idx]['enabled'] = 'false';
						$array['v_gateways'][$idx]['description'] = 'Cloned from source - configure before enabling';
					}

					$p = new permissions;
					$p->add('gateway_add', 'temp');
					$database = new database;
					$database->app_name = 'reseller';
					$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
					$database->save($array);
					unset($array);
					$p->delete('gateway_add', 'temp');
				}
			}

			//clone dialplan entries
			$sql = "select * from v_dialplans where domain_uuid = :domain_uuid ";
			$parameters['domain_uuid'] = $source_uuid;
			$database = new database;
			$dialplans = $database->select($sql, $parameters, 'all');
			unset($parameters);

			if (is_array($dialplans) && sizeof($dialplans) > 0) {
				foreach ($dialplans as $idx => $dp) {
					$new_dp_uuid = uuid();
					$array['v_dialplans'][$idx]['dialplan_uuid'] = $new_dp_uuid;
					$array['v_dialplans'][$idx]['domain_uuid'] = $target_uuid;
					$array['v_dialplans'][$idx]['dialplan_name'] = $dp['dialplan_name'] ?? '';
					$array['v_dialplans'][$idx]['dialplan_context'] = $dp['dialplan_context'] ?? '';
					$array['v_dialplans'][$idx]['dialplan_enabled'] = $dp['dialplan_enabled'] ?? 'true';
					$array['v_dialplans'][$idx]['dialplan_description'] = $dp['dialplan_description'] ?? '';
				}

				$p = new permissions;
				$p->add('dialplan_add', 'temp');
				$database = new database;
				$database->app_name = 'reseller';
				$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
				$database->save($array);
				unset($array);
				$p->delete('dialplan_add', 'temp');
			}
		}

		/**
		 * Create an admin user for a domain
		 * @param string $domain_uuid
		 * @param string $username
		 * @param string $password
		 */
		private function create_domain_admin_user($domain_uuid, $username, $password) {
			$new_user_uuid = uuid();
			$salt = uuid();
			$password_hash = md5($salt . $password);

			$array['v_users'][0]['user_uuid'] = $new_user_uuid;
			$array['v_users'][0]['domain_uuid'] = $domain_uuid;
			$array['v_users'][0]['username'] = $username;
			$array['v_users'][0]['password'] = $password_hash;
			$array['v_users'][0]['salt'] = $salt;
			$array['v_users'][0]['user_enabled'] = 'true';
			$array['v_users'][0]['add_date'] = 'now()';
			$array['v_users'][0]['add_user'] = $_SESSION['user_uuid'] ?? '';

			$p = new permissions;
			$p->add('user_add', 'temp');
			$database = new database;
			$database->app_name = 'reseller';
			$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
			$database->save($array);
			unset($array);
			$p->delete('user_add', 'temp');

			//assign admin group to user
			$sql = "select group_uuid from v_groups where group_name = 'admin' limit 1 ";
			$database = new database;
			$row = $database->select($sql, null, 'row');
			if (is_array($row) && !empty($row['group_uuid'])) {
				$array['v_user_groups'][0]['user_group_uuid'] = uuid();
				$array['v_user_groups'][0]['domain_uuid'] = $domain_uuid;
				$array['v_user_groups'][0]['group_name'] = 'admin';
				$array['v_user_groups'][0]['group_uuid'] = $row['group_uuid'];
				$array['v_user_groups'][0]['user_uuid'] = $new_user_uuid;

				$p = new permissions;
				$p->add('user_group_add', 'temp');
				$database = new database;
				$database->app_name = 'reseller';
				$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
				$database->save($array);
				unset($array);
				$p->delete('user_group_add', 'temp');
			}
		}

		/**
		 * Suspend a domain owned by the reseller
		 * @param string $reseller_uuid
		 * @param string $domain_uuid
		 * @return bool
		 */
		public function suspend_domain($reseller_uuid, $domain_uuid) {
			//verify ownership
			if (!$this->owns_domain($reseller_uuid, $domain_uuid)) {
				return false;
			}

			//update reseller domain status
			$sql = "update v_reseller_domains set status = 'suspended' ";
			$sql .= "where reseller_uuid = :reseller_uuid ";
			$sql .= "and domain_uuid = :domain_uuid ";
			$parameters['reseller_uuid'] = $reseller_uuid;
			$parameters['domain_uuid'] = $domain_uuid;
			$database = new database;
			$database->execute($sql, $parameters);
			unset($parameters);

			//disable the domain
			$sql = "update v_domains set domain_enabled = 'false' ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$parameters['domain_uuid'] = $domain_uuid;

			$p = new permissions;
			$p->add('domain_edit', 'temp');

			$database = new database;
			$database->execute($sql, $parameters);
			unset($parameters);

			$p->delete('domain_edit', 'temp');

			$this->log_activity($reseller_uuid, 'domain_suspended', [
				'domain_uuid' => $domain_uuid,
			], $domain_uuid);

			return true;
		}

		/**
		 * Activate a domain owned by the reseller
		 * @param string $reseller_uuid
		 * @param string $domain_uuid
		 * @return bool
		 */
		public function activate_domain($reseller_uuid, $domain_uuid) {
			if (!$this->owns_domain($reseller_uuid, $domain_uuid)) {
				return false;
			}

			$sql = "update v_reseller_domains set status = 'active' ";
			$sql .= "where reseller_uuid = :reseller_uuid ";
			$sql .= "and domain_uuid = :domain_uuid ";
			$parameters['reseller_uuid'] = $reseller_uuid;
			$parameters['domain_uuid'] = $domain_uuid;
			$database = new database;
			$database->execute($sql, $parameters);
			unset($parameters);

			$sql = "update v_domains set domain_enabled = 'true' ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$parameters['domain_uuid'] = $domain_uuid;

			$p = new permissions;
			$p->add('domain_edit', 'temp');

			$database = new database;
			$database->execute($sql, $parameters);
			unset($parameters);

			$p->delete('domain_edit', 'temp');

			$this->log_activity($reseller_uuid, 'domain_activated', [
				'domain_uuid' => $domain_uuid,
			], $domain_uuid);

			return true;
		}

		/**
		 * Delete a domain owned by the reseller
		 * @param string $reseller_uuid
		 * @param string $domain_uuid
		 * @return bool
		 */
		public function delete_domain($reseller_uuid, $domain_uuid) {
			if (!$this->owns_domain($reseller_uuid, $domain_uuid)) {
				return false;
			}

			//get domain name for logging
			$sql = "select domain_name from v_domains where domain_uuid = :domain_uuid ";
			$parameters['domain_uuid'] = $domain_uuid;
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			$domain_name = is_array($row) ? $row['domain_name'] : '';
			unset($parameters);

			//remove reseller domain mapping
			$sql = "delete from v_reseller_domains ";
			$sql .= "where reseller_uuid = :reseller_uuid ";
			$sql .= "and domain_uuid = :domain_uuid ";
			$parameters['reseller_uuid'] = $reseller_uuid;
			$parameters['domain_uuid'] = $domain_uuid;
			$database = new database;
			$database->execute($sql, $parameters);
			unset($parameters);

			//delete the domain itself
			$sql = "delete from v_domains where domain_uuid = :domain_uuid ";
			$parameters['domain_uuid'] = $domain_uuid;

			$p = new permissions;
			$p->add('domain_delete', 'temp');

			$database = new database;
			$database->execute($sql, $parameters);
			unset($parameters);

			$p->delete('domain_delete', 'temp');

			$this->log_activity($reseller_uuid, 'domain_deleted', [
				'domain_uuid' => $domain_uuid,
				'domain_name' => $domain_name,
			], $domain_uuid);

			return true;
		}

		/**
		 * Check if the reseller has remaining quota for a resource type
		 * @param string $reseller_uuid
		 * @param string $resource_type - domains, extensions, gateways
		 * @return bool
		 */
		public function check_quota($reseller_uuid, $resource_type) {
			$remaining = $this->get_remaining_quota($reseller_uuid, $resource_type);
			return ($remaining > 0);
		}

		/**
		 * Get remaining quota for a resource type
		 * @param string $reseller_uuid
		 * @param string $resource_type - domains, extensions, gateways
		 * @return int
		 */
		public function get_remaining_quota($reseller_uuid, $resource_type) {
			$profile = $this->get_profile($reseller_uuid);
			if (!$profile) {
				return 0;
			}

			switch ($resource_type) {
				case 'domains':
					$max = (int) $profile['max_domains'];
					$sql = "select count(*) as num from v_reseller_domains where reseller_uuid = :reseller_uuid ";
					$parameters['reseller_uuid'] = $reseller_uuid;
					$database = new database;
					$row = $database->select($sql, $parameters, 'row');
					$used = is_array($row) ? (int) $row['num'] : 0;
					return $max - $used;

				case 'extensions':
					$max = (int) $profile['max_total_extensions'];
					$used = $this->count_total_resource($reseller_uuid, 'v_extensions', 'extension_uuid');
					return $max - $used;

				case 'gateways':
					$max = (int) $profile['max_total_gateways'];
					$used = $this->count_total_resource($reseller_uuid, 'v_gateways', 'gateway_uuid');
					return $max - $used;

				default:
					return 0;
			}
		}

		/**
		 * Count total resources across all reseller domains
		 * @param string $reseller_uuid
		 * @param string $table
		 * @param string $column
		 * @return int
		 */
		private function count_total_resource($reseller_uuid, $table, $column) {
			$sql = "select count(r." . $column . ") as num ";
			$sql .= "from " . $table . " as r ";
			$sql .= "inner join v_reseller_domains as rd on r.domain_uuid = rd.domain_uuid ";
			$sql .= "where rd.reseller_uuid = :reseller_uuid ";
			$parameters['reseller_uuid'] = $reseller_uuid;
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			return is_array($row) ? (int) $row['num'] : 0;
		}

		/**
		 * Calculate commission for a payment
		 * @param string $reseller_uuid
		 * @param string $payment_uuid
		 * @return array|false
		 */
		public function calculate_commission($reseller_uuid, $payment_uuid) {
			$profile = $this->get_profile($reseller_uuid);
			if (!$profile) {
				return false;
			}

			$commission_rate = (float) $profile['commission_rate'];
			if ($commission_rate <= 0) {
				return false;
			}

			//look up the payment from the billing plugin
			$sql = "select * from v_billing_payments where payment_uuid = :payment_uuid ";
			$parameters['payment_uuid'] = $payment_uuid;
			$database = new database;
			$payment = $database->select($sql, $parameters, 'row');
			unset($parameters);

			if (!is_array($payment) || sizeof($payment) == 0) {
				return false;
			}

			$payment_amount = (float) ($payment['amount'] ?? 0);
			$commission_amount = round($payment_amount * ($commission_rate / 100), 2);
			$currency = $payment['currency'] ?? 'USD';

			//record the commission
			$commission_uuid = uuid();
			$array['v_reseller_commissions'][0]['commission_uuid'] = $commission_uuid;
			$array['v_reseller_commissions'][0]['reseller_uuid'] = $reseller_uuid;
			$array['v_reseller_commissions'][0]['payment_uuid'] = $payment_uuid;
			$array['v_reseller_commissions'][0]['domain_uuid'] = $payment['domain_uuid'] ?? '';
			$array['v_reseller_commissions'][0]['amount'] = $commission_amount;
			$array['v_reseller_commissions'][0]['currency'] = $currency;
			$array['v_reseller_commissions'][0]['status'] = 'pending';
			$array['v_reseller_commissions'][0]['add_date'] = 'now()';

			$p = new permissions;
			$p->add('reseller_commissions_view', 'temp');

			$database = new database;
			$database->app_name = 'reseller';
			$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
			$database->save($array);
			unset($array);

			$p->delete('reseller_commissions_view', 'temp');

			return [
				'commission_uuid' => $commission_uuid,
				'amount' => $commission_amount,
				'currency' => $currency,
				'status' => 'pending',
			];
		}

		/**
		 * Log an activity
		 * @param string $reseller_uuid
		 * @param string $action
		 * @param array $details
		 * @param string|null $domain_uuid
		 */
		public function log_activity($reseller_uuid, $action, $details = [], $domain_uuid = null) {
			$array['v_reseller_activity_log'][0]['log_uuid'] = uuid();
			$array['v_reseller_activity_log'][0]['reseller_uuid'] = $reseller_uuid;
			$array['v_reseller_activity_log'][0]['domain_uuid'] = $domain_uuid ?? '';
			$array['v_reseller_activity_log'][0]['action'] = $action;
			$array['v_reseller_activity_log'][0]['details_json'] = json_encode($details);
			$array['v_reseller_activity_log'][0]['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
			$array['v_reseller_activity_log'][0]['add_date'] = 'now()';

			$p = new permissions;
			$p->add('reseller_activity_view', 'temp');

			$database = new database;
			$database->app_name = 'reseller';
			$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
			$database->save($array);
			unset($array);

			$p->delete('reseller_activity_view', 'temp');
		}

		/**
		 * Check if reseller has a specific permission on a resource
		 * @param string $reseller_uuid
		 * @param string $resource
		 * @param string $action - view, add, edit, delete
		 * @return bool
		 */
		public function has_permission($reseller_uuid, $resource, $action) {
			$column = 'can_' . $action;
			$allowed_columns = ['can_view', 'can_add', 'can_edit', 'can_delete'];
			if (!in_array($column, $allowed_columns)) {
				return false;
			}

			$sql = "select " . $column . " from v_reseller_permissions ";
			$sql .= "where reseller_uuid = :reseller_uuid ";
			$sql .= "and resource_type = :resource_type ";
			$parameters['reseller_uuid'] = $reseller_uuid;
			$parameters['resource_type'] = $resource;
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			if (is_array($row) && $row[$column] === 'true') {
				return true;
			}
			return false;
		}

		/**
		 * Get commission summary for a date range
		 * @param string $reseller_uuid
		 * @param string $date_from
		 * @param string $date_to
		 * @return array
		 */
		public function get_commission_summary($reseller_uuid, $date_from = null, $date_to = null) {
			$sql = "select ";
			$sql .= "coalesce(sum(case when status = 'pending' then amount else 0 end), 0) as total_pending, ";
			$sql .= "coalesce(sum(case when status = 'paid' then amount else 0 end), 0) as total_paid, ";
			$sql .= "coalesce(sum(amount), 0) as total_earnings, ";
			$sql .= "count(*) as total_count ";
			$sql .= "from v_reseller_commissions ";
			$sql .= "where reseller_uuid = :reseller_uuid ";
			$parameters['reseller_uuid'] = $reseller_uuid;

			if ($date_from) {
				$sql .= "and add_date >= :date_from ";
				$parameters['date_from'] = $date_from;
			}
			if ($date_to) {
				$sql .= "and add_date <= :date_to ";
				$parameters['date_to'] = $date_to;
			}

			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			if (is_array($row)) {
				return $row;
			}
			return [
				'total_pending' => 0,
				'total_paid' => 0,
				'total_earnings' => 0,
				'total_count' => 0,
			];
		}

		/**
		 * Generate a new API key pair
		 * @return array ['api_key' => string, 'api_secret' => string]
		 */
		public function generate_api_key() {
			$api_key = 'rk_' . bin2hex(random_bytes(16));
			$api_secret = 'rs_' . bin2hex(random_bytes(32));
			return [
				'api_key' => $api_key,
				'api_secret' => $api_secret,
			];
		}

		/**
		 * Verify an API key and secret
		 * @param string $key
		 * @param string $secret
		 * @return array|false - reseller profile on success, false on failure
		 */
		public function verify_api_key($key, $secret) {
			$sql = "select * from v_reseller_profiles ";
			$sql .= "where api_key = :api_key ";
			$sql .= "and enabled = 'true' ";
			$sql .= "and status = 'active' ";
			$parameters['api_key'] = $key;
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			if (is_array($row) && sizeof($row) > 0) {
				//verify the secret using hash comparison
				if (hash_equals($row['api_secret'], hash('sha256', $secret))) {
					return $row;
				}
			}
			return false;
		}

		/**
		 * Check if a reseller owns a specific domain
		 * @param string $reseller_uuid
		 * @param string $domain_uuid
		 * @return bool
		 */
		public function owns_domain($reseller_uuid, $domain_uuid) {
			$sql = "select count(*) as num from v_reseller_domains ";
			$sql .= "where reseller_uuid = :reseller_uuid ";
			$sql .= "and domain_uuid = :domain_uuid ";
			$parameters['reseller_uuid'] = $reseller_uuid;
			$parameters['domain_uuid'] = $domain_uuid;
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			return (is_array($row) && (int)$row['num'] > 0);
		}

		/**
		 * Set a domain setting
		 * @param string $domain_uuid
		 * @param string $category
		 * @param string $subcategory
		 * @param string $type
		 * @param string $value
		 */
		private function set_domain_setting($domain_uuid, $category, $subcategory, $type, $value) {
			$array['v_domain_settings'][0]['domain_setting_uuid'] = uuid();
			$array['v_domain_settings'][0]['domain_uuid'] = $domain_uuid;
			$array['v_domain_settings'][0]['domain_setting_category'] = $category;
			$array['v_domain_settings'][0]['domain_setting_subcategory'] = $subcategory;
			$array['v_domain_settings'][0]['domain_setting_name'] = $type;
			$array['v_domain_settings'][0]['domain_setting_value'] = $value;
			$array['v_domain_settings'][0]['domain_setting_enabled'] = 'true';

			$p = new permissions;
			$p->add('domain_setting_add', 'temp');

			$database = new database;
			$database->app_name = 'reseller';
			$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
			$database->save($array);
			unset($array);

			$p->delete('domain_setting_add', 'temp');
		}

		/**
		 * Create a billing subscription for a domain
		 * @param string $reseller_uuid
		 * @param string $domain_uuid
		 * @param string $plan_uuid
		 * @return string|null subscription UUID or null
		 */
		private function create_billing_subscription($reseller_uuid, $domain_uuid, $plan_uuid) {
			//get the reseller plan
			$sql = "select * from v_reseller_plans where reseller_plan_uuid = :plan_uuid and reseller_uuid = :reseller_uuid ";
			$parameters['plan_uuid'] = $plan_uuid;
			$parameters['reseller_uuid'] = $reseller_uuid;
			$database = new database;
			$plan = $database->select($sql, $parameters, 'row');
			unset($parameters);

			if (!is_array($plan) || sizeof($plan) == 0) {
				return null;
			}

			//check if billing plugin tables exist
			$sql = "select count(*) as num from information_schema.tables where table_name = 'v_billing_subscriptions' ";
			$database = new database;
			$row = $database->select($sql, null, 'row');
			if (!is_array($row) || (int)$row['num'] == 0) {
				return null;
			}

			$subscription_uuid = uuid();
			$array['billing_subscriptions'][0]['subscription_uuid'] = $subscription_uuid;
			$array['billing_subscriptions'][0]['domain_uuid'] = $domain_uuid;
			$array['billing_subscriptions'][0]['plan_uuid'] = $plan['base_plan_uuid'] ?? '';
			$array['billing_subscriptions'][0]['status'] = 'active';
			$array['billing_subscriptions'][0]['start_date'] = 'now()';
			$array['billing_subscriptions'][0]['add_date'] = 'now()';

			$p = new permissions;
			$p->add('billing_subscription_add', 'temp');

			$database = new database;
			$database->app_name = 'reseller';
			$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
			$database->save($array);
			unset($array);

			$p->delete('billing_subscription_add', 'temp');

			return $subscription_uuid;
		}

		/**
		 * Get all reseller profiles (admin function)
		 * @return array
		 */
		public function get_all_resellers() {
			$sql = "select rp.*, ";
			$sql .= "(select count(*) from v_reseller_domains rd where rd.reseller_uuid = rp.reseller_uuid) as domain_count ";
			$sql .= "from v_reseller_profiles rp ";
			$sql .= "order by rp.company_name asc ";
			$database = new database;
			$result = $database->select($sql, null, 'all');
			if (is_array($result) && sizeof($result) > 0) {
				return $result;
			}
			return [];
		}

		/**
		 * Get statistics for admin dashboard
		 * @return array
		 */
		public function get_admin_stats() {
			$stats = [];

			//total resellers
			$sql = "select count(*) as num from v_reseller_profiles ";
			$database = new database;
			$row = $database->select($sql, null, 'row');
			$stats['total_resellers'] = is_array($row) ? (int) $row['num'] : 0;

			//active resellers
			$sql = "select count(*) as num from v_reseller_profiles where status = 'active' ";
			$database = new database;
			$row = $database->select($sql, null, 'row');
			$stats['active_resellers'] = is_array($row) ? (int) $row['num'] : 0;

			//total managed domains
			$sql = "select count(*) as num from v_reseller_domains ";
			$database = new database;
			$row = $database->select($sql, null, 'row');
			$stats['total_domains'] = is_array($row) ? (int) $row['num'] : 0;

			//active managed domains
			$sql = "select count(*) as num from v_reseller_domains where status = 'active' ";
			$database = new database;
			$row = $database->select($sql, null, 'row');
			$stats['active_domains'] = is_array($row) ? (int) $row['num'] : 0;

			//total pending commissions
			$sql = "select coalesce(sum(amount), 0) as total from v_reseller_commissions where status = 'pending' ";
			$database = new database;
			$row = $database->select($sql, null, 'row');
			$stats['total_pending_commissions'] = is_array($row) ? (float) $row['total'] : 0;

			return $stats;
		}
	}
}

?>
