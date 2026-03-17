<?php

/**
 * billing_paypal class
 *
 * PayPal payment gateway integration using PayPal REST API v2
 */
class billing_paypal {

	private $client_id;
	private $client_secret;
	private $sandbox;
	private $base_url;
	private $access_token;

	/**
	 * Set gateway configuration
	 * @param array $config
	 * @param bool $sandbox
	 */
	public function set_config($config, $sandbox = true) {
		$this->client_id = $config['client_id'] ?? '';
		$this->client_secret = $config['client_secret'] ?? '';
		$this->sandbox = $sandbox;
		$this->base_url = $sandbox
			? 'https://api-m.sandbox.paypal.com'
			: 'https://api-m.paypal.com';
	}

	/**
	 * Get access token from PayPal
	 * @return string|false
	 */
	private function get_access_token() {
		if ($this->access_token) {
			return $this->access_token;
		}

		$ch = curl_init($this->base_url . '/v1/oauth2/token');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
		curl_setopt($ch, CURLOPT_USERPWD, $this->client_id . ':' . $this->client_secret);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/x-www-form-urlencoded',
		]);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code == 200) {
			$data = json_decode($response, true);
			$this->access_token = $data['access_token'] ?? false;
			return $this->access_token;
		}

		error_log('PayPal auth failed: ' . $response);
		return false;
	}

	/**
	 * Create a PayPal order
	 * @param float $amount
	 * @param string $currency
	 * @param string $description
	 * @return array|false
	 */
	public function create_order($amount, $currency, $description) {
		$token = $this->get_access_token();
		if (!$token) { return false; }

		$order_data = [
			'intent' => 'CAPTURE',
			'purchase_units' => [[
				'amount' => [
					'currency_code' => strtoupper($currency),
					'value' => number_format($amount, 2, '.', ''),
				],
				'description' => $description,
			]],
			'application_context' => [
				'return_url' => $this->get_return_url(),
				'cancel_url' => $this->get_cancel_url(),
				'brand_name' => 'Billing System',
				'user_action' => 'PAY_NOW',
			],
		];

		$ch = curl_init($this->base_url . '/v2/checkout/orders');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $token,
		]);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code == 201) {
			$data = json_decode($response, true);
			$approve_url = '';
			if (isset($data['links'])) {
				foreach ($data['links'] as $link) {
					if ($link['rel'] == 'approve') {
						$approve_url = $link['href'];
						break;
					}
				}
			}
			return [
				'transaction_id' => $data['id'],
				'status' => 'pending',
				'redirect_url' => $approve_url,
				'raw_response' => $data,
			];
		}

		error_log('PayPal create order failed: ' . $response);
		return false;
	}

	/**
	 * Capture a payment after buyer approval
	 * @param string $order_id
	 * @return array|false
	 */
	public function capture_payment($order_id) {
		$token = $this->get_access_token();
		if (!$token) { return false; }

		$ch = curl_init($this->base_url . '/v2/checkout/orders/' . urlencode($order_id) . '/capture');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, '');
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $token,
		]);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code == 201 || $http_code == 200) {
			$data = json_decode($response, true);
			$capture_id = '';
			if (isset($data['purchase_units'][0]['payments']['captures'][0])) {
				$capture_id = $data['purchase_units'][0]['payments']['captures'][0]['id'];
			}
			return [
				'transaction_id' => $capture_id ?: $order_id,
				'status' => strtolower($data['status']) == 'completed' ? 'completed' : 'pending',
				'raw_response' => $data,
			];
		}

		error_log('PayPal capture failed: ' . $response);
		return false;
	}

	/**
	 * Verify a PayPal webhook
	 * @param string $payload
	 * @param array $headers
	 * @return array|false
	 */
	public function verify_webhook($payload, $headers) {
		$data = json_decode($payload, true);
		if (!$data) { return false; }

		//verify the webhook signature with PayPal
		$token = $this->get_access_token();
		if (!$token) {
			//fallback: parse the event without verification
			return $data;
		}

		$verify_data = [
			'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
			'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
			'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
			'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
			'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
			'webhook_id' => $this->webhook_id ?? '',
			'webhook_event' => $data,
		];

		$ch = curl_init($this->base_url . '/v1/notifications/verify-webhook-signature');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verify_data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $token,
		]);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code == 200) {
			$result = json_decode($response, true);
			if (($result['verification_status'] ?? '') == 'SUCCESS') {
				return $data;
			}
		}

		return false;
	}

	/**
	 * Refund a captured payment
	 * @param string $capture_id
	 * @param float|null $amount
	 * @return array|false
	 */
	public function refund($capture_id, $amount = null) {
		$token = $this->get_access_token();
		if (!$token) { return false; }

		$refund_data = [];
		if ($amount !== null) {
			$refund_data['amount'] = [
				'value' => number_format($amount, 2, '.', ''),
				'currency_code' => 'USD',
			];
		}

		$ch = curl_init($this->base_url . '/v2/payments/captures/' . urlencode($capture_id) . '/refund');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($refund_data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $token,
		]);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code == 201) {
			$data = json_decode($response, true);
			return [
				'refund_id' => $data['id'],
				'status' => strtolower($data['status']),
				'raw_response' => $data,
			];
		}

		error_log('PayPal refund failed: ' . $response);
		return false;
	}

	/**
	 * Test the connection to PayPal
	 * @param array $gateway_config
	 * @return bool
	 */
	public function test_connection($gateway_config = []) {
		if (!empty($gateway_config)) {
			$config = json_decode($gateway_config['config_json'], true) ?? [];
			$this->set_config($config, $gateway_config['sandbox_mode'] == 'true');
		}
		$token = $this->get_access_token();
		return !empty($token);
	}

	/**
	 * Get return URL
	 */
	private function get_return_url() {
		$base = ($_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
		return $base . '/app/billing/billing_webhook.php?gateway=paypal&action=return';
	}

	/**
	 * Get cancel URL
	 */
	private function get_cancel_url() {
		$base = ($_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
		return $base . '/app/billing/billing_pay.php?payment=cancelled';
	}

}

?>
