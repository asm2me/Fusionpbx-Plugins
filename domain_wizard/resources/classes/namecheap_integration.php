<?php
/**
 * Namecheap Integration Helper
 * Handles automatic subdomain registration for voipat.com subdomains
 */

class namecheap_integration {

	private $api_user;
	private $api_key;
	private $api_url = 'https://api.namecheap.com/xml.response';
	private $sandbox_mode = false;

	public function __construct($api_user = null, $api_key = null, $sandbox = false) {
		$this->api_user = $api_user ?? getenv('NAMECHEAP_API_USER');
		$this->api_key = $api_key ?? getenv('NAMECHEAP_API_KEY');
		$this->sandbox_mode = $sandbox;
		
		if ($this->sandbox_mode) {
			$this->api_url = 'https://api.sandbox.namecheap.com/xml.response';
		}
	}

	/**
	 * Register a subdomain with Namecheap
	 * @param string $subdomain - The subdomain part (e.g., "acmecorp" for "acmecorp.voipat.com")
	 * @param string $target_ip - The IP address to point the subdomain to
	 * @return array - Result with status and message
	 */
	public function register_subdomain($subdomain, $target_ip) {
		if (empty($this->api_user) || empty($this->api_key)) {
			return ['status' => 'error', 'message' => 'Namecheap API credentials not configured'];
		}

		//validate subdomain format
		if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?$/', $subdomain)) {
			return ['status' => 'error', 'message' => 'Invalid subdomain format'];
		}

		//validate IP address
		if (!filter_var($target_ip, FILTER_VALIDATE_IP)) {
			return ['status' => 'error', 'message' => 'Invalid IP address'];
		}

		try {
			//prepare API request for adding a host record
			$params = [
				'ApiUser' => $this->api_user,
				'ApiKey' => $this->api_key,
				'UserName' => $this->api_user,
				'Command' => 'namecheap.domains.dns.setHosts',
				'ClientIp' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
				'DomainName' => 'voipat.com',
				'TLD' => 'com',
				'SLD' => 'voipat',
				'HostName1' => $subdomain,
				'RecordType1' => 'A',
				'Address1' => $target_ip,
				'TTL1' => 1800,
			];

			//build query string
			$query = http_build_query($params);
			
			//make API request
			$response = $this->make_api_request($query);

			if ($response === false) {
				return ['status' => 'error', 'message' => 'Failed to communicate with Namecheap API'];
			}

			//parse response
			$xml = simplexml_load_string($response);
			
			if (!$xml) {
				return ['status' => 'error', 'message' => 'Invalid API response'];
			}

			//check for errors
			if (isset($xml->Errors->Error)) {
				$error_msg = (string)$xml->Errors->Error;
				return ['status' => 'error', 'message' => 'Namecheap API Error: ' . $error_msg];
			}

			//check for success
			if (isset($xml->CommandResponse->DomainDNSSetHostsResult)) {
				return [
					'status' => 'success',
					'message' => 'Subdomain registered successfully',
					'subdomain' => $subdomain . '.voipat.com',
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
			//get current hosts first
			$get_params = [
				'ApiUser' => $this->api_user,
				'ApiKey' => $this->api_key,
				'UserName' => $this->api_user,
				'Command' => 'namecheap.domains.dns.getHosts',
				'ClientIp' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
				'DomainName' => 'voipat.com',
			];

			$query = http_build_query($get_params);
			$response = $this->make_api_request($query);
			$xml = simplexml_load_string($response);

			if (!$xml) {
				return ['status' => 'error', 'message' => 'Failed to retrieve current DNS records'];
			}

			//rebuild host list without the subdomain to delete
			$set_params = [
				'ApiUser' => $this->api_user,
				'ApiKey' => $this->api_key,
				'UserName' => $this->api_user,
				'Command' => 'namecheap.domains.dns.setHosts',
				'ClientIp' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
				'DomainName' => 'voipat.com',
				'TLD' => 'com',
				'SLD' => 'voipat',
			];

			$host_index = 1;
			if (isset($xml->CommandResponse->DomainDNSGetHostsResult->host)) {
				foreach ($xml->CommandResponse->DomainDNSGetHostsResult->host as $host) {
					$name = (string)$host->Name;
					
					//skip the subdomain to delete
					if ($name === $subdomain) {
						continue;
					}

					$set_params['HostName' . $host_index] = $name;
					$set_params['RecordType' . $host_index] = (string)$host->Type;
					$set_params['Address' . $host_index] = (string)$host->Address;
					$set_params['TTL' . $host_index] = (string)$host->TTL;
					
					$host_index++;
				}
			}

			$query = http_build_query($set_params);
			$response = $this->make_api_request($query);
			$xml = simplexml_load_string($response);

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
function register_namecheap_subdomain($subdomain, $target_ip) {
	$nc = new namecheap_integration();
	return $nc->register_subdomain($subdomain, $target_ip);
}
