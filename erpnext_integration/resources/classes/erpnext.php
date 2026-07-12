<?php

/*
	FusionPBX - ERPNext / Frappe Integration
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	ERPNext REST client + shared helpers.

	Usage:
		$erpnext = new erpnext($domain_uuid);
		if ($erpnext->is_enabled()) { ... }
*/

if (!class_exists('erpnext')) {
	class erpnext {

		public $domain_uuid;
		public $config = [];      // erpnext.* settings for this domain
		public $last_error = '';
		public $last_http_code = 0;

		public function __construct($domain_uuid = null) {
			$this->domain_uuid = $domain_uuid;
			$this->load_config();
		}

		/**
		 * Load erpnext.* settings for this domain.
		 *
		 * The integration is configured strictly per-domain: connection values
		 * (url, api_key, api_secret, shared_secret, ...) come ONLY from
		 * v_domain_settings for this domain_uuid. There is no global-default
		 * fallback for connection values, so each domain has an independent
		 * ERPNext connection and a domain with nothing configured is inert.
		 *
		 * The global v_default_settings rows exist only so the fields appear in
		 * Advanced > Default Settings; they are intentionally NOT merged here.
		 */
		public function load_config() {
			$database = new database;
			$this->config = [];

			if (empty($this->domain_uuid)) {
				return; //no domain => no config (strict per-domain)
			}

			$sql = "select domain_setting_subcategory as name, domain_setting_value as value ";
			$sql .= "from v_domain_settings ";
			$sql .= "where domain_uuid = :domain_uuid and domain_setting_category = 'erpnext' and domain_setting_enabled = 'true'";
			$parameters['domain_uuid'] = $this->domain_uuid;
			$rows = $database->select($sql, $parameters, 'all') ?: [];
			foreach ($rows as $r) {
				$this->config[$r['name']] = $r['value'];
			}
			unset($sql, $parameters, $rows);
		}

		public function get($name, $default = '') {
			return isset($this->config[$name]) && $this->config[$name] !== '' ? $this->config[$name] : $default;
		}

		public function is_enabled() {
			return $this->get('enabled') === 'true'
				&& $this->get('url') !== ''
				&& $this->get('api_key') !== ''
				&& $this->get('api_secret') !== '';
		}

		/**
		 * Normalize a phone number to a comparable key: strip non-digits, keep the
		 * trailing (up to 12) significant digits so +1-555, 001555 and 555 collate.
		 */
		public static function normalize_number($number) {
			$digits = preg_replace('/\D+/', '', (string)$number);
			if (strlen($digits) > 12) {
				$digits = substr($digits, -12);
			}
			return $digits;
		}

		/**
		 * Low-level authenticated request to the ERPNext REST API.
		 * $path is relative to the ERPNext base URL (e.g. /api/resource/Call Log).
		 * Returns the decoded JSON array on success, or false on failure.
		 */
		public function request($method, $path, $body = null) {
			$this->last_error = '';
			$this->last_http_code = 0;

			$base = rtrim($this->get('url'), '/');
			if ($base === '') {
				$this->last_error = 'erpnext url not configured';
				return false;
			}

			$url = $base . '/' . ltrim($path, '/');
			//encode spaces in the path (doctype names) but keep the query string intact
			$url = str_replace(' ', '%20', $url);

			$headers = [
				'Authorization: token ' . $this->get('api_key') . ':' . $this->get('api_secret'),
				'Accept: application/json',
			];

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);

			$verify = ($this->get('verify_tls', 'true') === 'true');
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);

			if ($body !== null) {
				$headers[] = 'Content-Type: application/json';
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$response = curl_exec($ch);
			$this->last_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			if ($response === false) {
				$this->last_error = 'curl: ' . curl_error($ch);
				curl_close($ch);
				return false;
			}
			curl_close($ch);

			$decoded = json_decode($response, true);
			if ($this->last_http_code < 200 || $this->last_http_code >= 300) {
				$msg = '';
				if (is_array($decoded)) {
					$msg = $decoded['exception'] ?? $decoded['message'] ?? ($decoded['_server_messages'] ?? '');
					if (is_array($msg)) $msg = implode('; ', $msg);
				}
				$this->last_error = 'http ' . $this->last_http_code . ($msg ? ': ' . $msg : '');
				return false;
			}

			return $decoded;
		}

		/**
		 * Verify credentials by fetching the logged-in user.
		 * Returns the username on success or false.
		 */
		public function test_connection() {
			$result = $this->request('GET', '/api/method/frappe.auth.get_logged_user');
			if ($result === false) return false;
			return $result['message'] ?? true;
		}

		/**
		 * Create a Call Log document in ERPNext.
		 * $call is an associative array of Call Log fields (id, from, to, status,
		 * duration, type, start_time, end_time, recording_url).
		 * Returns the created docname on success, false on failure.
		 */
		public function create_call_log($call) {
			$result = $this->request('POST', '/api/resource/Call Log', $call);
			if ($result === false) return false;
			return $result['data']['name'] ?? ($result['data']['id'] ?? true);
		}

		/**
		 * Notify ERPNext of an inbound call so it can raise a screen-pop.
		 * Calls a whitelisted method in the companion Frappe app.
		 */
		public function notify_incoming_call($payload) {
			$result = $this->request('POST', '/api/method/fusionpbx_integration.api.incoming_call', $payload);
			return $result !== false;
		}

		/**
		 * Look up a contact by phone number via the companion Frappe app.
		 * Returns ['name'=>..., 'doctype'=>..., 'docname'=>...] or null.
		 */
		public function lookup_contact($number) {
			$q = '/api/method/fusionpbx_integration.api.lookup_contact?number=' . rawurlencode($number);
			$result = $this->request('GET', $q);
			if ($result === false || empty($result['message'])) return null;
			return $result['message'];
		}
	}
}

?>
