<?php

/**
 * billing_binance class
 *
 * Binance Pay payment gateway integration
 */
class billing_binance {

	private $api_key;
	private $api_secret;
	private $merchant_id;
	private $base_url = 'https://bpay.binanceapi.com';

	/**
	 * Set gateway configuration
	 * @param array $config
	 */
	public function set_config($config) {
		$this->api_key = $config['api_key'] ?? '';
		$this->api_secret = $config['api_secret'] ?? '';
		$this->merchant_id = $config['merchant_id'] ?? '';
	}

	/**
	 * Generate nonce string
	 * @param int $length
	 * @return string
	 */
	private function generate_nonce($length = 32) {
		$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$nonce = '';
		for ($i = 0; $i < $length; $i++) {
			$nonce .= $characters[random_int(0, strlen($characters) - 1)];
		}
		return $nonce;
	}

	/**
	 * Generate signature for Binance Pay API
	 * @param string $timestamp
	 * @param string $nonce
	 * @param string $body
	 * @return string
	 */
	private function generate_signature($timestamp, $nonce, $body) {
		$payload = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
		return strtoupper(hash_hmac('sha512', $payload, $this->api_secret));
	}

	/**
	 * Create a Binance Pay order
	 * @param float $amount
	 * @param string $currency
	 * @param string $description
	 * @return array|false
	 */
	public function create_order($amount, $currency, $description) {
		$merchant_trade_no = 'BP' . date('YmdHis') . rand(1000, 9999);

		$order_data = [
			'env' => [
				'terminalType' => 'WEB',
			],
			'merchantTradeNo' => $merchant_trade_no,
			'orderAmount' => number_format($amount, 2, '.', ''),
			'currency' => strtoupper($currency),
			'goods' => [
				'goodsType' => '02',
				'goodsCategory' => '6000',
				'referenceGoodsId' => $merchant_trade_no,
				'goodsName' => $description,
			],
		];

		$base_url = ($_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
		$order_data['returnUrl'] = $base_url . '/app/billing/billing_pay.php?payment=success';
		$order_data['cancelUrl'] = $base_url . '/app/billing/billing_pay.php?payment=cancelled';
		$order_data['webhookUrl'] = $base_url . '/app/billing/billing_webhook.php?gateway=binance';

		$response = $this->api_request('/binancepay/openapi/v2/order', $order_data);

		if ($response && $response['status'] == 'SUCCESS') {
			$data = $response['data'] ?? [];
			return [
				'transaction_id' => $data['prepayId'] ?? $merchant_trade_no,
				'merchant_trade_no' => $merchant_trade_no,
				'status' => 'pending',
				'redirect_url' => $data['universalUrl'] ?? ($data['checkoutUrl'] ?? ''),
				'raw_response' => $response,
			];
		}

		error_log('Binance Pay create order failed: ' . json_encode($response));
		return false;
	}

	/**
	 * Query order status
	 * @param string $merchant_trade_no
	 * @return array|false
	 */
	public function query_order($merchant_trade_no) {
		$query_data = [
			'merchantTradeNo' => $merchant_trade_no,
		];

		$response = $this->api_request('/binancepay/openapi/v2/order/query', $query_data);

		if ($response && $response['status'] == 'SUCCESS') {
			$data = $response['data'] ?? [];
			$status = 'pending';
			switch ($data['status'] ?? '') {
				case 'PAID':
					$status = 'completed';
					break;
				case 'EXPIRED':
				case 'CANCELLED':
					$status = 'failed';
					break;
				case 'INITIAL':
				case 'PENDING':
					$status = 'pending';
					break;
			}

			return [
				'transaction_id' => $data['transactionId'] ?? '',
				'merchant_trade_no' => $merchant_trade_no,
				'status' => $status,
				'amount' => $data['orderAmount'] ?? 0,
				'raw_response' => $response,
			];
		}

		return false;
	}

	/**
	 * Verify a Binance Pay webhook
	 * @param string $payload
	 * @return array|false
	 */
	public function verify_webhook($payload) {
		$data = json_decode($payload, true);
		if (!$data) { return false; }

		//Binance Pay sends the bizType and data in the webhook
		//verify by checking the signature in headers
		$headers = getallheaders();
		$timestamp = $headers['Binancepay-Timestamp'] ?? '';
		$nonce = $headers['Binancepay-Nonce'] ?? '';
		$signature = $headers['Binancepay-Signature'] ?? '';

		if (!empty($timestamp) && !empty($nonce) && !empty($signature)) {
			$expected = $this->generate_signature($timestamp, $nonce, $payload);
			if (hash_equals($expected, $signature)) {
				return $data;
			}
			error_log('Binance Pay webhook signature mismatch');
			return false;
		}

		//if no signature headers, return data but log warning
		error_log('Binance Pay webhook: no signature headers present');
		return $data;
	}

	/**
	 * Test connection to Binance Pay
	 * @param array $gateway_config
	 * @return bool
	 */
	public function test_connection($gateway_config = []) {
		if (!empty($gateway_config)) {
			$config = json_decode($gateway_config['config_json'], true) ?? [];
			$this->set_config($config);
		}

		//try querying a non-existent order to verify credentials
		$test_data = [
			'merchantTradeNo' => 'TEST' . time(),
		];

		$response = $this->api_request('/binancepay/openapi/v2/order/query', $test_data);

		//if we get a proper API response (even an error about order not found), credentials are valid
		if ($response && isset($response['status'])) {
			return true;
		}

		return false;
	}

	/**
	 * Make an API request to Binance Pay
	 * @param string $endpoint
	 * @param array $data
	 * @return array|false
	 */
	private function api_request($endpoint, $data) {
		$url = $this->base_url . $endpoint;
		$body = json_encode($data);
		$timestamp = round(microtime(true) * 1000);
		$nonce = $this->generate_nonce();
		$signature = $this->generate_signature($timestamp, $nonce, $body);

		$headers = [
			'Content-Type: application/json',
			'BinancePay-Timestamp: ' . $timestamp,
			'BinancePay-Nonce: ' . $nonce,
			'BinancePay-Certificate-SN: ' . $this->api_key,
			'BinancePay-Signature: ' . $signature,
		];

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error) {
			error_log('Binance Pay cURL error: ' . $error);
			return false;
		}

		$result = json_decode($response, true);

		if ($http_code >= 200 && $http_code < 300) {
			return $result;
		}

		error_log('Binance Pay API error (' . $http_code . '): ' . $response);
		return false;
	}

}

?>
