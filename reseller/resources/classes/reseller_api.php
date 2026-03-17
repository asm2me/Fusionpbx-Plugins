<?php

/**
 * reseller_api class
 *
 * Provides REST API functionality for reseller operations.
 * Handles authentication, request routing, and response formatting.
 */
if (!class_exists('reseller_api')) {
	class reseller_api {

		private $reseller;
		private $reseller_profile;
		private $rate_limit = 60; //requests per minute

		public function __construct() {
			$this->reseller = new reseller;
		}

		/**
		 * Authenticate using API key and secret
		 * @param string $api_key
		 * @param string $api_secret
		 * @return bool
		 */
		public function authenticate($api_key, $api_secret) {
			$profile = $this->reseller->verify_api_key($api_key, $api_secret);
			if ($profile) {
				$this->reseller_profile = $profile;
				return true;
			}
			return false;
		}

		/**
		 * Check rate limit for the authenticated reseller
		 * @return bool true if within limit
		 */
		private function check_rate_limit() {
			if (!$this->reseller_profile) {
				return false;
			}

			$reseller_uuid = $this->reseller_profile['reseller_uuid'];
			$window_start = date('Y-m-d H:i:s', time() - 60);

			$sql = "select count(*) as num from v_reseller_activity_log ";
			$sql .= "where reseller_uuid = :reseller_uuid ";
			$sql .= "and action = 'api_request' ";
			$sql .= "and add_date >= :window_start ";
			$parameters['reseller_uuid'] = $reseller_uuid;
			$parameters['window_start'] = $window_start;
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			$count = is_array($row) ? (int) $row['num'] : 0;

			return ($count < $this->rate_limit);
		}

		/**
		 * Handle an incoming API request
		 * @param string $method - GET, POST, PUT, DELETE
		 * @param string $endpoint - e.g., /domains, /domains/{id}, /plans, /commissions, /usage
		 * @param array $data - request body data
		 * @return array ['status' => int, 'body' => array]
		 */
		public function handle_request($method, $endpoint, $data = []) {
			if (!$this->reseller_profile) {
				return $this->response(401, ['error' => 'Authentication required.']);
			}

			if (!$this->check_rate_limit()) {
				return $this->response(429, ['error' => 'Rate limit exceeded. Please try again later.']);
			}

			$reseller_uuid = $this->reseller_profile['reseller_uuid'];

			//log the API request
			$this->reseller->log_activity($reseller_uuid, 'api_request', [
				'method' => $method,
				'endpoint' => $endpoint,
			]);

			//parse the endpoint
			$parts = explode('/', trim($endpoint, '/'));
			$resource = $parts[0] ?? '';
			$resource_id = $parts[1] ?? null;

			switch ($resource) {
				case 'domains':
					return $this->handle_domains($method, $resource_id, $data, $reseller_uuid);

				case 'plans':
					return $this->handle_plans($method, $resource_id, $reseller_uuid);

				case 'commissions':
					return $this->handle_commissions($method, $data, $reseller_uuid);

				case 'usage':
					return $this->handle_usage($reseller_uuid);

				default:
					return $this->response(404, ['error' => 'Invalid endpoint.']);
			}
		}

		/**
		 * Handle /domains endpoints
		 */
		private function handle_domains($method, $domain_id, $data, $reseller_uuid) {
			switch ($method) {
				case 'GET':
					if ($domain_id) {
						//get single domain
						$domains = $this->reseller->get_domains($reseller_uuid);
						foreach ($domains as $domain) {
							if ($domain['domain_uuid'] === $domain_id) {
								return $this->response(200, ['domain' => $domain]);
							}
						}
						return $this->response(404, ['error' => 'Domain not found.']);
					} else {
						//list all domains
						$domains = $this->reseller->get_domains($reseller_uuid);
						return $this->response(200, ['domains' => $domains, 'total' => count($domains)]);
					}

				case 'POST':
					if ($domain_id) {
						return $this->response(405, ['error' => 'Method not allowed.']);
					}
					//create domain
					$required = ['domain_name'];
					foreach ($required as $field) {
						if (empty($data[$field])) {
							return $this->response(400, ['error' => "Field '{$field}' is required."]);
						}
					}
					$result = $this->reseller->create_domain($reseller_uuid, $data);
					if ($result['success']) {
						return $this->response(201, $result);
					} else {
						return $this->response(400, $result);
					}

				case 'PUT':
					if (!$domain_id) {
						return $this->response(400, ['error' => 'Domain ID required.']);
					}
					//update domain (activate/suspend)
					if (isset($data['action'])) {
						switch ($data['action']) {
							case 'suspend':
								$success = $this->reseller->suspend_domain($reseller_uuid, $domain_id);
								return $success
									? $this->response(200, ['message' => 'Domain suspended.'])
									: $this->response(403, ['error' => 'Cannot suspend domain.']);
							case 'activate':
								$success = $this->reseller->activate_domain($reseller_uuid, $domain_id);
								return $success
									? $this->response(200, ['message' => 'Domain activated.'])
									: $this->response(403, ['error' => 'Cannot activate domain.']);
							default:
								return $this->response(400, ['error' => 'Invalid action.']);
						}
					}
					return $this->response(400, ['error' => 'Action required.']);

				case 'DELETE':
					if (!$domain_id) {
						return $this->response(400, ['error' => 'Domain ID required.']);
					}
					$success = $this->reseller->delete_domain($reseller_uuid, $domain_id);
					return $success
						? $this->response(200, ['message' => 'Domain deleted.'])
						: $this->response(403, ['error' => 'Cannot delete domain.']);

				default:
					return $this->response(405, ['error' => 'Method not allowed.']);
			}
		}

		/**
		 * Handle /plans endpoints
		 */
		private function handle_plans($method, $plan_id, $reseller_uuid) {
			if ($method !== 'GET') {
				return $this->response(405, ['error' => 'Method not allowed. Plans are read-only via API.']);
			}

			$sql = "select * from v_reseller_plans ";
			$sql .= "where reseller_uuid = :reseller_uuid ";
			$sql .= "and enabled = 'true' ";
			$parameters['reseller_uuid'] = $reseller_uuid;

			if ($plan_id) {
				$sql .= "and reseller_plan_uuid = :plan_uuid ";
				$parameters['plan_uuid'] = $plan_id;
			}

			$sql .= "order by plan_name asc ";
			$database = new database;
			$result = $database->select($sql, $parameters, 'all');

			if ($plan_id) {
				if (is_array($result) && sizeof($result) > 0) {
					return $this->response(200, ['plan' => $result[0]]);
				}
				return $this->response(404, ['error' => 'Plan not found.']);
			}

			return $this->response(200, ['plans' => is_array($result) ? $result : [], 'total' => is_array($result) ? count($result) : 0]);
		}

		/**
		 * Handle /commissions endpoints
		 */
		private function handle_commissions($method, $data, $reseller_uuid) {
			if ($method !== 'GET') {
				return $this->response(405, ['error' => 'Method not allowed.']);
			}

			$date_from = $data['date_from'] ?? null;
			$date_to = $data['date_to'] ?? null;

			$summary = $this->reseller->get_commission_summary($reseller_uuid, $date_from, $date_to);

			//also get individual commissions
			$sql = "select * from v_reseller_commissions ";
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

			$sql .= "order by add_date desc ";
			$database = new database;
			$commissions = $database->select($sql, $parameters, 'all');

			return $this->response(200, [
				'summary' => $summary,
				'commissions' => is_array($commissions) ? $commissions : [],
			]);
		}

		/**
		 * Handle /usage endpoint
		 */
		private function handle_usage($reseller_uuid) {
			$profile = $this->reseller->get_profile($reseller_uuid);
			$domains = $this->reseller->get_domains($reseller_uuid);

			$usage = [
				'domains' => [
					'used' => count($domains),
					'max' => (int) $profile['max_domains'],
					'remaining' => $this->reseller->get_remaining_quota($reseller_uuid, 'domains'),
				],
				'extensions' => [
					'max' => (int) $profile['max_total_extensions'],
					'remaining' => $this->reseller->get_remaining_quota($reseller_uuid, 'extensions'),
				],
				'gateways' => [
					'max' => (int) $profile['max_total_gateways'],
					'remaining' => $this->reseller->get_remaining_quota($reseller_uuid, 'gateways'),
				],
			];
			$usage['extensions']['used'] = $usage['extensions']['max'] - $usage['extensions']['remaining'];
			$usage['gateways']['used'] = $usage['gateways']['max'] - $usage['gateways']['remaining'];

			return $this->response(200, ['usage' => $usage]);
		}

		/**
		 * Build a standardized response array
		 * @param int $status HTTP status code
		 * @param array $body Response body
		 * @return array
		 */
		private function response($status, $body) {
			return [
				'status' => $status,
				'body' => $body,
			];
		}
	}
}

?>
