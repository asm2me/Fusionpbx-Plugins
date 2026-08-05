<?php

/*
	FusionPBX - Support Tickets SMS Notifications
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Sends SMS notifications for ticket events (created, status_changed,
	updated) through a Dinstar or GoIP GSM gateway.

	Configured strictly per-domain via v_domain_settings, category
	'tickets_sms' (see app_config.php / app_defaults.php for the fields).
	A domain with nothing configured is inert - failures here never
	block the ticket operation that triggered them.

	Usage:
		$sms = new ticket_sms($domain_uuid);
		$sms->notify($ticket, 'created');
*/

if (!class_exists('ticket_sms')) {
	class ticket_sms {

		public $domain_uuid;
		public $config = [];
		public $last_error = '';

		public function __construct($domain_uuid = null) {
			$this->domain_uuid = $domain_uuid;
			$this->load_config();
		}

		public function load_config() {
			$database = new database;
			$this->config = [];

			if (empty($this->domain_uuid)) {
				return; //no domain => no config (strict per-domain)
			}

			$sql = "select domain_setting_subcategory as name, domain_setting_value as value ";
			$sql .= "from v_domain_settings ";
			$sql .= "where domain_uuid = :domain_uuid and domain_setting_category = 'tickets_sms' and domain_setting_enabled = 'true'";
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

		/**
		 * A gateway type + host are set - enough to attempt sending.
		 */
		public function is_configured() {
			return in_array($this->get('gateway_type'), ['dinstar', 'goip']) && $this->get('gateway_host') !== '';
		}

		/**
		 * Configured AND the domain has turned the master switch on.
		 * This is what ticket-event notifications require.
		 */
		public function is_enabled() {
			return $this->get('enabled') === 'true' && $this->is_configured();
		}

		/**
		 * Send a single SMS through the configured gateway.
		 * Returns true on success, false on failure (see $this->last_error).
		 */
		public function send($to_number, $message) {
			$this->last_error = '';

			if (!$this->is_enabled()) {
				$this->last_error = 'sms gateway not configured or disabled';
				return false;
			}
			return $this->dispatch($to_number, $message);
		}

		/**
		 * Send a test SMS using whatever is currently configured, ignoring
		 * the master "enabled" switch - lets an admin verify credentials
		 * work before turning notifications on for real.
		 */
		public function send_test($to_number, $message) {
			$this->last_error = '';

			if (!$this->is_configured()) {
				$this->last_error = 'sms gateway not configured (set gateway type and host first)';
				return false;
			}
			return $this->dispatch($to_number, $message);
		}

		private function dispatch($to_number, $message) {
			if (empty($to_number)) {
				$this->last_error = 'no destination number';
				return false;
			}

			$type = $this->get('gateway_type');
			if ($type === 'dinstar') {
				return $this->send_dinstar($to_number, $message);
			}
			if ($type === 'goip') {
				return $this->send_goip($to_number, $message);
			}

			$this->last_error = 'unknown gateway_type: ' . $type;
			return false;
		}

		/**
		 * Dinstar GSM Gateway HTTP API (v202011) - POST /api/send_sms
		 * https://www.dinstar.com/tools/ - "Instructions for Using New API"
		 */
		private function send_dinstar($to_number, $message) {
			$host = $this->get('gateway_host');
			$port = $this->get('gateway_port', '443');
			$channel = intval($this->get('gateway_channel', '0'));
			$verify = ($this->get('verify_ssl', 'false') === 'true');

			$url = 'https://' . $host . ':' . $port . '/api/send_sms';
			$body = [
				'text' => $message,
				'encoding' => 'unicode',
				'port' => [$channel],
				'param' => [
					['number' => $to_number],
				],
			];

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
			curl_setopt($ch, CURLOPT_USERPWD, $this->get('gateway_username') . ':' . $this->get('gateway_password'));
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

			$response = curl_exec($ch);
			if ($response === false) {
				$this->last_error = 'curl: ' . curl_error($ch);
				curl_close($ch);
				return false;
			}
			curl_close($ch);

			$decoded = json_decode($response, true);
			$error_code = $decoded['error_code'] ?? null;
			if ($error_code !== 202) {
				$this->last_error = 'dinstar error_code: ' . ($error_code ?? 'unparseable response: ' . $response);
				return false;
			}
			return true;
		}

		/**
		 * GoIP GSM Gateway HTTP API - GET /default/en_US/send.html
		 * Success responses start with "Sending,"; failures start with "ERROR".
		 */
		private function send_goip($to_number, $message) {
			$host = $this->get('gateway_host');
			$port = $this->get('gateway_port', '80');
			$channel = $this->get('gateway_channel', '0');

			$url = 'http://' . $host . ':' . $port . '/default/en_US/send.html?' . http_build_query([
				'u' => $this->get('gateway_username'),
				'p' => $this->get('gateway_password'),
				'l' => $channel,
				'n' => $to_number,
				'm' => $message,
			]);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

			$response = curl_exec($ch);
			if ($response === false) {
				$this->last_error = 'curl: ' . curl_error($ch);
				curl_close($ch);
				return false;
			}
			curl_close($ch);

			if (stripos(trim($response), 'Sending') !== 0) {
				$this->last_error = 'goip response: ' . $response;
				return false;
			}
			return true;
		}

		/**
		 * Compose and send the SMS(es) for a ticket event, per this domain's
		 * configured recipients/events. $event is one of:
		 * 'created', 'status_changed', 'updated'.
		 * $ticket must include: ticket_number, subject, status, and a
		 * customer number in either contact_phone (preferred) or call_number.
		 * Best-effort: never throws, returns void.
		 */
		public function notify($ticket, $event) {
			if (!$this->is_enabled()) {
				return;
			}

			$number = $ticket['ticket_number'] ?? '';
			$subject = $ticket['subject'] ?? '';
			$status = $ticket['status'] ?? '';

			$messages = [
				'created' => [
					'support' => "New ticket $number: $subject",
					'customer' => "Your ticket $number has been received: $subject",
				],
				'status_changed' => [
					'support' => "Ticket $number status changed to $status",
					'customer' => "Your ticket $number status is now: $status",
				],
				'updated' => [
					'support' => "Ticket $number was updated",
					'customer' => "Your ticket $number has a new update",
				],
			];

			if (!isset($messages[$event])) {
				return;
			}

			if ($this->get('notify_support_enabled') === 'true'
				&& in_array($event, $this->events_list('notify_support_events'))) {
				$support_number = $this->get('notify_support_number');
				if (!empty($support_number)) {
					$this->send($support_number, $messages[$event]['support']);
				}
			}

			if ($this->get('notify_customer_enabled') === 'true'
				&& in_array($event, $this->events_list('notify_customer_events'))) {
				$customer_number = !empty($ticket['contact_phone']) ? $ticket['contact_phone'] : ($ticket['call_number'] ?? '');
				if (!empty($customer_number)) {
					$this->send($customer_number, $messages[$event]['customer']);
				}
			}
		}

		private function events_list($setting_name) {
			$raw = $this->get($setting_name);
			if (empty($raw)) {
				return [];
			}
			return array_map('trim', explode(',', $raw));
		}
	}
}

?>
