<?php
/**
 * Namecheap Integration Helper
 * Handles automatic subdomain registration for voipat.com subdomains
 */

class namecheap_integration {

	private $api_user;
	private $api_key;
	private $client_ip;
	private $domain_name;
	private $sld;
	private $tld;
	private $api_url = 'https://api.namecheap.com/xml.response';
	private $sandbox_mode = false;

	public function __construct($api_user = null, $api_key = null, $sandbox = false, $base_domain = null) {
		$this->api_user = $api_user ?? getenv('NAMECHEAP_API_USER');
		$this->api_key = $api_key ?? getenv('NAMECHEAP_API_KEY');
		$this->sandbox_mode = $sandbox;

		if ($this->sandbox_mode) {
			$this->api_url = 'https://api.sandbox.namecheap.com/xml.response';
		}

		//the registrar-side domain that subdomains are created under
		$this->domain_name = $base_domain ?? (getenv('NAMECHEAP_BASE_DOMAIN') ?: 'voipat.com');
		$dot_pos = strrpos($this->domain_name, '.');
		$this->sld = $dot_pos !== false ? substr($this->domain_name, 0, $dot_pos) : $this->domain_name;
		$this->tld = $dot_pos !== false ? substr($this->domain_name, $dot_pos + 1) : 'com';

		//Namecheap requires ClientIp to be a whitelisted IP tied to the API account -
		//it must NOT be the visiting browser's IP, so never fall back to $_SERVER['REMOTE_ADDR']
		$this->client_ip = $this->resolve_client_ip();
	}

	/**
	 * Automatically register a domain in Namecheap when it is a subdomain of the
	 * configured base domain (default voipat.com). Safe to call for any domain -
	 * it is a no-op (returns false, no exception) when the domain doesn't match.
	 *
	 * @param string $domain_uuid  Domain UUID (used to store the result as a domain setting)
	 * @param string $domain_name  Full domain name as created in FusionPBX
	 * @param array  $log          Log array (by reference) - OK:/WARNING: lines are appended
	 * @return bool  true if a DNS record was successfully created/updated
	 */
	public static function auto_register_for_domain($domain_uuid, $domain_name, array &$log = []) {
		$base_domain = getenv('NAMECHEAP_BASE_DOMAIN') ?: 'voipat.com';
		$suffix = '.' . $base_domain;

		if (strlen($domain_name) <= strlen($suffix) || strcasecmp(substr($domain_name, -strlen($suffix)), $suffix) !== 0) {
			//not a subdomain of the base domain - nothing to do in Namecheap
			return false;
		}

		$subdomain = substr($domain_name, 0, -strlen($suffix));

		if (empty($subdomain) || !preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$/', $subdomain)) {
			$log[] = 'WARNING: Skipped Namecheap DNS registration - "' . $subdomain . '" is not a valid subdomain label.';
			return false;
		}

		$target_ip = getenv('NAMECHEAP_TARGET_IP');
		if (empty($target_ip) || !filter_var($target_ip, FILTER_VALIDATE_IP)) {
			$target_ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
		}

		try {
			$nc = new self();
			$result = $nc->register_subdomain($subdomain, $target_ip);
		}
		catch (\Throwable $e) {
			$log[] = 'WARNING: Namecheap DNS registration threw an exception - ' . $e->getMessage();
			return false;
		}

		if (($result['status'] ?? '') === 'success') {
			$log[] = 'OK: Namecheap DNS registered - ' . $result['subdomain'] . ' -> ' . $target_ip;
			if (!empty($domain_uuid)) {
				self::save_domain_setting($domain_uuid, 'registration', 'namecheap_registered', 'text', 'true');
				self::save_domain_setting($domain_uuid, 'registration', 'namecheap_ip', 'text', $target_ip);
			}
			return true;
		}

		$log[] = 'WARNING: Namecheap DNS registration failed - ' . ($result['message'] ?? 'Unknown error');
		if (!empty($domain_uuid)) {
			self::save_domain_setting($domain_uuid, 'registration', 'namecheap_registered', 'text', 'false');
		}
		return false;
	}

	/**
	 * Register (or update) a subdomain host record with Namecheap
	 * @param string $subdomain - The subdomain part (e.g., "acmecorp" for "acmecorp.voipat.com")
	 * @param string $target_ip - The IP address to point the subdomain to
	 * @return array - Result with status and message
	 */
	public function register_subdomain($subdomain, $target_ip) {
		if (empty($this->api_user) || empty($this->api_key)) {
			return ['status' => 'error', 'message' => 'Namecheap API credentials not configured'];
		}

		//validate subdomain format (allow nested labels, e.g. "foo.bar")
		if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*$/', $subdomain)) {
			return ['status' => 'error', 'message' => 'Invalid subdomain format'];
		}

		//validate IP address
		if (!filter_var($target_ip, FILTER_VALIDATE_IP)) {
			return ['status' => 'error', 'message' => 'Invalid IP address'];
		}

		try {
			//fetch the existing host records first - setHosts REPLACES the entire zone,
			//so skipping this step would silently wipe every other DNS record on the domain
			$existing_hosts = $this->get_hosts();
			if ($existing_hosts === false) {
				return ['status' => 'error', 'message' => 'Failed to retrieve current DNS records'];
			}

			//drop any existing record for this exact host name - we're replacing it
			$hosts = [];
			foreach ($existing_hosts as $host) {
				if (strcasecmp($host['Name'], $subdomain) === 0) {
					continue;
				}
				$hosts[] = $host;
			}

			//add the new/updated host record
			$hosts[] = [
				'Name' => $subdomain,
				'Type' => 'A',
				'Address' => $target_ip,
				'TTL' => '1800',
			];

			$response = $this->set_hosts($hosts);
			if ($response === false) {
				return ['status' => 'error', 'message' => 'Failed to communicate with Namecheap API'];
			}

			$xml = simplexml_load_string($response);
			if (!$xml) {
				return ['status' => 'error', 'message' => 'Invalid API response'];
			}

			if (isset($xml->Errors->Error)) {
				$error_msg = (string)$xml->Errors->Error;
				return ['status' => 'error', 'message' => 'Namecheap API Error: ' . $error_msg];
			}

			if (isset($xml->CommandResponse->DomainDNSSetHostsResult)) {
				return [
					'status' => 'success',
					'message' => 'Subdomain registered successfully',
					'subdomain' => $subdomain . '.' . $this->domain_name,
					'ip' => $target_ip,
					'ttl' => 1800
				];
			}

			return ['status' => 'error', 'message' => 'Unexpected API response'];

		} catch (Exception $e) {
			return ['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()];
		}
	}

	/**
	 * Delete a subdomain record
	 * @param string $subdomain - The subdomain to delete
	 * @return array - Result
	 */
	public function delete_subdomain($subdomain) {
		if (empty($this->api_user) || empty($this->api_key)) {
			return ['status' => 'error', 'message' => 'Namecheap API credentials not configured'];
		}

		try {
			$existing_hosts = $this->get_hosts();
			if ($existing_hosts === false) {
				return ['status' => 'error', 'message' => 'Failed to retrieve current DNS records'];
			}

			//rebuild host list without the subdomain to delete
			$hosts = [];
			foreach ($existing_hosts as $host) {
				if (strcasecmp($host['Name'], $subdomain) === 0) {
					continue;
				}
				$hosts[] = $host;
			}

			$response = $this->set_hosts($hosts);
			if ($response === false) {
				return ['status' => 'error', 'message' => 'Failed to communicate with Namecheap API'];
			}

			$xml = simplexml_load_string($response);
			if (!$xml) {
				return ['status' => 'error', 'message' => 'Invalid API response'];
			}

			if (isset($xml->Errors->Error)) {
				$error_msg = (string)$xml->Errors->Error;
				return ['status' => 'error', 'message' => 'Error: ' . $error_msg];
			}

			return ['status' => 'success', 'message' => 'Subdomain deleted successfully'];

		} catch (Exception $e) {
			return ['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()];
		}
	}

	/**
	 * Get the current host records for the configured domain
	 * @return array|false  List of ['Name'=>, 'Type'=>, 'Address'=>, 'TTL'=>] or false on failure
	 */
	private function get_hosts() {
		$params = [
			'ApiUser' => $this->api_user,
			'ApiKey' => $this->api_key,
			'UserName' => $this->api_user,
			'Command' => 'namecheap.domains.dns.getHosts',
			'ClientIp' => $this->client_ip,
			'DomainName' => $this->domain_name,
		];

		$response = $this->make_api_request(http_build_query($params));
		if ($response === false) {
			return false;
		}

		$xml = simplexml_load_string($response);
		if (!$xml) {
			return false;
		}

		if (isset($xml->Errors->Error)) {
			return false;
		}

		$hosts = [];
		if (isset($xml->CommandResponse->DomainDNSGetHostsResult->host)) {
			foreach ($xml->CommandResponse->DomainDNSGetHostsResult->host as $host) {
				$hosts[] = [
					'Name' => (string)$host['Name'],
					'Type' => (string)$host['Type'],
					'Address' => (string)$host['Address'],
					'TTL' => (string)$host['TTL'],
				];
			}
		}

		return $hosts;
	}

	/**
	 * Push a full list of host records to Namecheap (replaces the entire zone)
	 * @param array $hosts  List of ['Name'=>, 'Type'=>, 'Address'=>, 'TTL'=>]
	 * @return string|false  Raw response body or false on transport error
	 */
	private function set_hosts(array $hosts) {
		$params = [
			'ApiUser' => $this->api_user,
			'ApiKey' => $this->api_key,
			'UserName' => $this->api_user,
			'Command' => 'namecheap.domains.dns.setHosts',
			'ClientIp' => $this->client_ip,
			'DomainName' => $this->domain_name,
			'TLD' => $this->tld,
			'SLD' => $this->sld,
		];

		foreach (array_values($hosts) as $i => $host) {
			$n = $i + 1;
			$params['HostName' . $n] = $host['Name'];
			$params['RecordType' . $n] = $host['Type'];
			$params['Address' . $n] = $host['Address'];
			$params['TTL' . $n] = $host['TTL'];
		}

		return $this->make_api_request(http_build_query($params));
	}

	/**
	 * Resolve the IP address to send as ClientIp. Namecheap requires this to be an
	 * IP that is whitelisted under the API account - not the visiting browser's IP.
	 * @return string
	 */
	private function resolve_client_ip() {
		$ip = getenv('NAMECHEAP_CLIENT_IP');
		if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
			return $ip;
		}

		if (!empty($_SERVER['SERVER_ADDR']) && filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP)) {
			return $_SERVER['SERVER_ADDR'];
		}

		$host_ip = gethostbyname(gethostname());
		if (filter_var($host_ip, FILTER_VALIDATE_IP)) {
			return $host_ip;
		}

		return '127.0.0.1';
	}

	/**
	 * Save a domain setting (used to record the Namecheap registration result)
	 */
	private static function save_domain_setting($domain_uuid, $category, $subcategory, $type, $value) {
		$array['v_domain_settings'][0]['domain_setting_uuid'] = uuid();
		$array['v_domain_settings'][0]['domain_uuid'] = $domain_uuid;
		$array['v_domain_settings'][0]['domain_setting_category'] = $category;
		$array['v_domain_settings'][0]['domain_setting_subcategory'] = $subcategory;
		$array['v_domain_settings'][0]['domain_setting_name'] = $type;
		$array['v_domain_settings'][0]['domain_setting_value'] = $value;
		$array['v_domain_settings'][0]['domain_setting_enabled'] = 'true';

		$p = new permissions;
		$p->add('v_domain_settings_add', 'temp');

		$database = new database;
		$database->app_name = 'domain_wizard';
		$database->app_uuid = '6e1d4a7c-2b8f-4e3d-9c5a-1d7b0e6f3a2c';
		$database->save($array);

		$p->delete('v_domain_settings_add', 'temp');
	}

	/**
	 * Make HTTP request to Namecheap API
	 * @param string $query - Query string
	 * @return string|false - Response body or false on error
	 */
	private function make_api_request($query) {
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => $this->api_url . '?' . $query,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		]);

		$response = curl_exec($ch);
		$errno = curl_errno($ch);
		curl_close($ch);

		if ($errno !== 0) {
			return false;
		}

		return $response;
	}
}

/**
 * Convenience function for registering subdomains
 */
if (!function_exists('register_namecheap_subdomain')) {
	function register_namecheap_subdomain($subdomain, $target_ip) {
		$nc = new namecheap_integration();
		return $nc->register_subdomain($subdomain, $target_ip);
	}
}
