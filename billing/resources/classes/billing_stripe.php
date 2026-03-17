<?php

/**
 * billing_stripe class
 *
 * Stripe payment gateway integration using Stripe API
 */
class billing_stripe {

	private $publishable_key;
	private $secret_key;
	private $webhook_secret;
	private $base_url = 'https://api.stripe.com';

	/**
	 * Set gateway configuration
	 * @param array $config
	 */
	public function set_config($config) {
		$this->publishable_key = $config['publishable_key'] ?? '';
		$this->secret_key = $config['secret_key'] ?? '';
		$this->webhook_secret = $config['webhook_secret'] ?? '';
	}

	/**
	 * Create a Stripe Checkout Session
	 * @param float $amount
	 * @param string $currency
	 * @param string $description
	 * @param string $success_url
	 * @param string $cancel_url
	 * @return array|false
	 */
	public function create_checkout_session($amount, $currency, $description, $success_url, $cancel_url) {
		$amount_cents = intval(round($amount * 100));

		$data = [
			'payment_method_types' => ['card'],
			'line_items' => [[
				'price_data' => [
					'currency' => strtolower($currency),
					'product_data' => [
						'name' => $description,
					],
					'unit_amount' => $amount_cents,
				],
				'quantity' => 1,
			]],
			'mode' => 'payment',
			'success_url' => $success_url . '&session_id={CHECKOUT_SESSION_ID}',
			'cancel_url' => $cancel_url,
		];

		$response = $this->api_request('POST', '/v1/checkout/sessions', $data);

		if ($response && isset($response['id'])) {
			return [
				'transaction_id' => $response['id'],
				'status' => 'pending',
				'redirect_url' => $response['url'],
				'raw_response' => $response,
			];
		}

		return false;
	}

	/**
	 * Verify a Stripe webhook signature
	 * @param string $payload
	 * @param string $sig_header
	 * @return array|false
	 */
	public function verify_webhook($payload, $sig_header) {
		if (empty($this->webhook_secret)) {
			return json_decode($payload, true);
		}

		$elements = explode(',', $sig_header);
		$timestamp = null;
		$signatures = [];

		foreach ($elements as $element) {
			$parts = explode('=', $element, 2);
			if (count($parts) == 2) {
				if ($parts[0] == 't') {
					$timestamp = $parts[1];
				}
				elseif ($parts[0] == 'v1') {
					$signatures[] = $parts[1];
				}
			}
		}

		if (!$timestamp || empty($signatures)) {
			return false;
		}

		//verify timestamp is within tolerance (5 minutes)
		if (abs(time() - intval($timestamp)) > 300) {
			return false;
		}

		//compute expected signature
		$signed_payload = $timestamp . '.' . $payload;
		$expected_sig = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

		$verified = false;
		foreach ($signatures as $sig) {
			if (hash_equals($expected_sig, $sig)) {
				$verified = true;
				break;
			}
		}

		if ($verified) {
			return json_decode($payload, true);
		}

		return false;
	}

	/**
	 * Get payment status from a checkout session
	 * @param string $session_id
	 * @return array|false
	 */
	public function get_payment_status($session_id) {
		$response = $this->api_request('GET', '/v1/checkout/sessions/' . urlencode($session_id));

		if ($response && isset($response['id'])) {
			$status = 'pending';
			if ($response['payment_status'] == 'paid') {
				$status = 'completed';
			}
			elseif ($response['payment_status'] == 'unpaid') {
				$status = 'pending';
			}

			return [
				'session_id' => $response['id'],
				'payment_intent' => $response['payment_intent'] ?? '',
				'status' => $status,
				'amount_total' => ($response['amount_total'] ?? 0) / 100,
				'currency' => strtoupper($response['currency'] ?? ''),
				'raw_response' => $response,
			];
		}

		return false;
	}

	/**
	 * Refund a payment
	 * @param string $payment_intent_id
	 * @param float|null $amount Partial refund amount, null for full refund
	 * @return array|false
	 */
	public function refund($payment_intent_id, $amount = null) {
		$data = [
			'payment_intent' => $payment_intent_id,
		];

		if ($amount !== null) {
			$data['amount'] = intval(round($amount * 100));
		}

		$response = $this->api_request('POST', '/v1/refunds', $data);

		if ($response && isset($response['id'])) {
			return [
				'refund_id' => $response['id'],
				'status' => $response['status'],
				'amount' => ($response['amount'] ?? 0) / 100,
				'raw_response' => $response,
			];
		}

		return false;
	}

	/**
	 * Test the connection to Stripe
	 * @param array $gateway_config
	 * @return bool
	 */
	public function test_connection($gateway_config = []) {
		if (!empty($gateway_config)) {
			$config = json_decode($gateway_config['config_json'], true) ?? [];
			$this->set_config($config);
		}

		$response = $this->api_request('GET', '/v1/balance');
		return ($response && isset($response['available']));
	}

	/**
	 * Make an API request to Stripe
	 * @param string $method
	 * @param string $endpoint
	 * @param array $data
	 * @return array|false
	 */
	private function api_request($method, $endpoint, $data = []) {
		$url = $this->base_url . $endpoint;

		$ch = curl_init();

		if ($method == 'GET') {
			if (!empty($data)) {
				$url .= '?' . http_build_query($data);
			}
			curl_setopt($ch, CURLOPT_HTTPGET, true);
		}
		elseif ($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->flatten_array($data)));
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $this->secret_key,
		]);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error) {
			error_log('Stripe cURL error: ' . $error);
			return false;
		}

		$result = json_decode($response, true);

		if ($http_code >= 200 && $http_code < 300) {
			return $result;
		}

		error_log('Stripe API error (' . $http_code . '): ' . $response);
		return false;
	}

	/**
	 * Flatten nested arrays for Stripe API format
	 * @param array $array
	 * @param string $prefix
	 * @return array
	 */
	private function flatten_array($array, $prefix = '') {
		$result = [];
		foreach ($array as $key => $value) {
			$new_key = $prefix ? $prefix . '[' . $key . ']' : $key;
			if (is_array($value)) {
				$result = array_merge($result, $this->flatten_array($value, $new_key));
			}
			else {
				$result[$new_key] = $value;
			}
		}
		return $result;
	}

}

?>
