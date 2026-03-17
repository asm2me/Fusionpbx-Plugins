<?php

/**
 * Webhook endpoint for payment gateway callbacks
 *
 * This file handles incoming webhooks from PayPal, Stripe, and Binance Pay.
 * It does not require session authentication as it's called by external services.
 */

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";

//determine the gateway
	$gateway = $_REQUEST['gateway'] ?? '';
	$action = $_REQUEST['action'] ?? '';

//log the webhook
	$raw_payload = file_get_contents('php://input');
	error_log('Billing webhook received: gateway=' . $gateway . ' action=' . $action);

//get gateway configuration
	$sql = "select * from v_billing_payment_gateways where gateway_name = :gateway_name and enabled = 'true' ";
	$parameters['gateway_name'] = $gateway;
	$database = new database;
	$gateway_config = $database->select($sql, $parameters, 'row');
	unset($sql, $parameters);

	if (!is_array($gateway_config)) {
		http_response_code(404);
		echo json_encode(['error' => 'Gateway not found or disabled']);
		exit;
	}

	$config = json_decode($gateway_config['config_json'], true) ?? [];

//load billing class
	require_once __DIR__ . "/resources/classes/billing.php";
	$billing = new billing;

//handle gateway-specific webhooks
	switch ($gateway) {

		case 'paypal':
			require_once __DIR__ . "/resources/classes/billing_paypal.php";
			$paypal = new billing_paypal;
			$paypal->set_config($config, $gateway_config['sandbox_mode'] == 'true');

			//handle return from PayPal
			if ($action == 'return') {
				$paypal_token = $_GET['token'] ?? '';
				if (!empty($paypal_token)) {
					//capture the payment
					$result = $paypal->capture_payment($paypal_token);
					if ($result && $result['status'] == 'completed') {
						//find the pending payment by transaction_id
						$sql = "select payment_uuid from v_billing_payments ";
						$sql .= "where transaction_id = :transaction_id and status = 'pending' ";
						$sql .= "order by add_date desc limit 1 ";
						$parameters['transaction_id'] = $paypal_token;
						$database = new database;
						$payment_uuid = $database->select($sql, $parameters, 'column');
						unset($sql, $parameters);

						if (is_uuid($payment_uuid)) {
							$billing->complete_payment($payment_uuid, $result['transaction_id'], $result['raw_response']);
						}

						header('Location: /app/billing/billing_pay.php?payment=success');
						exit;
					}
				}
				header('Location: /app/billing/billing_pay.php?payment=failed');
				exit;
			}

			//handle PayPal IPN/webhook
			$headers = getallheaders();
			$event = $paypal->verify_webhook($raw_payload, $headers);

			if ($event) {
				$event_type = $event['event_type'] ?? '';

				switch ($event_type) {
					case 'CHECKOUT.ORDER.APPROVED':
						$order_id = $event['resource']['id'] ?? '';
						if ($order_id) {
							$capture = $paypal->capture_payment($order_id);
							if ($capture && $capture['status'] == 'completed') {
								$sql = "select payment_uuid from v_billing_payments ";
								$sql .= "where transaction_id = :transaction_id and status = 'pending' ";
								$parameters['transaction_id'] = $order_id;
								$database = new database;
								$payment_uuid = $database->select($sql, $parameters, 'column');
								unset($sql, $parameters);

								if (is_uuid($payment_uuid)) {
									$billing->complete_payment($payment_uuid, $capture['transaction_id'], $capture['raw_response']);
								}
							}
						}
						break;

					case 'PAYMENT.CAPTURE.COMPLETED':
						$capture_id = $event['resource']['id'] ?? '';
						$order_id = $event['resource']['supplementary_data']['related_ids']['order_id'] ?? '';
						if ($order_id) {
							$sql = "select payment_uuid from v_billing_payments ";
							$sql .= "where transaction_id = :transaction_id and status = 'pending' ";
							$parameters['transaction_id'] = $order_id;
							$database = new database;
							$payment_uuid = $database->select($sql, $parameters, 'column');
							unset($sql, $parameters);

							if (is_uuid($payment_uuid)) {
								$billing->complete_payment($payment_uuid, $capture_id, $event['resource']);
							}
						}
						break;

					case 'PAYMENT.CAPTURE.DENIED':
					case 'PAYMENT.CAPTURE.REFUNDED':
						//mark payment as failed/refunded
						$status = $event_type == 'PAYMENT.CAPTURE.REFUNDED' ? 'refunded' : 'failed';
						$capture_id = $event['resource']['id'] ?? '';
						if ($capture_id) {
							$sql = "update v_billing_payments set status = :status ";
							$sql .= "where transaction_id = :transaction_id ";
							$parameters['status'] = $status;
							$parameters['transaction_id'] = $capture_id;
							$database = new database;
							$database->execute($sql, $parameters);
							unset($sql, $parameters);
						}
						break;
				}

				http_response_code(200);
				echo json_encode(['status' => 'ok']);
			}
			else {
				http_response_code(400);
				echo json_encode(['error' => 'Invalid webhook signature']);
			}
			break;

		case 'stripe':
			require_once __DIR__ . "/resources/classes/billing_stripe.php";
			$stripe = new billing_stripe;
			$stripe->set_config($config);

			$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
			$event = $stripe->verify_webhook($raw_payload, $sig_header);

			if ($event) {
				$event_type = $event['type'] ?? '';
				$event_data = $event['data']['object'] ?? [];

				switch ($event_type) {
					case 'checkout.session.completed':
						$session_id = $event_data['id'] ?? '';
						$payment_intent = $event_data['payment_intent'] ?? '';

						if ($event_data['payment_status'] == 'paid') {
							//find the pending payment
							$sql = "select payment_uuid from v_billing_payments ";
							$sql .= "where transaction_id = :transaction_id and status = 'pending' ";
							$parameters['transaction_id'] = $session_id;
							$database = new database;
							$payment_uuid = $database->select($sql, $parameters, 'column');
							unset($sql, $parameters);

							if (is_uuid($payment_uuid)) {
								$billing->complete_payment($payment_uuid, $payment_intent ?: $session_id, $event_data);
							}
						}
						break;

					case 'payment_intent.succeeded':
						$payment_intent_id = $event_data['id'] ?? '';
						//already handled by checkout.session.completed in most cases
						break;

					case 'payment_intent.payment_failed':
						$session_id = $event_data['id'] ?? '';
						$sql = "select p.payment_uuid, p.invoice_uuid, i.subscription_uuid ";
						$sql .= "from v_billing_payments as p ";
						$sql .= "left join v_billing_invoices as i on p.invoice_uuid = i.invoice_uuid ";
						$sql .= "where p.transaction_id = :transaction_id and p.status = 'pending' ";
						$parameters['transaction_id'] = $session_id;
						$database = new database;
						$payment_row = $database->select($sql, $parameters, 'row');
						unset($sql, $parameters);

						if (is_array($payment_row) && is_uuid($payment_row['payment_uuid'])) {
							$array['v_billing_payments'][0]['payment_uuid'] = $payment_row['payment_uuid'];
							$array['v_billing_payments'][0]['status'] = 'failed';
							$array['v_billing_payments'][0]['gateway_response_json'] = json_encode($event_data);

							$p = new permissions;
							$p->add('billing_payment_edit', 'temp');
							$database = new database;
							$database->app_name = 'billing';
							$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
							$database->save($array);
							unset($array);
							$p->delete('billing_payment_edit', 'temp');

							//send payment failed notice
							if (is_uuid($payment_row['subscription_uuid'])) {
								$billing->send_notice($payment_row['subscription_uuid'], 'payment_failed');
							}
						}
						break;

					case 'charge.refunded':
						$payment_intent_id = $event_data['payment_intent'] ?? '';
						if ($payment_intent_id) {
							$sql = "update v_billing_payments set status = 'refunded' ";
							$sql .= "where transaction_id = :transaction_id ";
							$parameters['transaction_id'] = $payment_intent_id;
							$database = new database;
							$database->execute($sql, $parameters);
							unset($sql, $parameters);
						}
						break;
				}

				http_response_code(200);
				echo json_encode(['status' => 'ok']);
			}
			else {
				http_response_code(400);
				echo json_encode(['error' => 'Invalid webhook signature']);
			}
			break;

		case 'binance':
			require_once __DIR__ . "/resources/classes/billing_binance.php";
			$binance = new billing_binance;
			$binance->set_config($config);

			$event = $binance->verify_webhook($raw_payload);

			if ($event) {
				$biz_type = $event['bizType'] ?? '';
				$biz_data = json_decode($event['data'] ?? '{}', true);

				if ($biz_type == 'PAY') {
					$merchant_trade_no = $biz_data['merchantTradeNo'] ?? '';
					$transaction_id = $biz_data['transactionId'] ?? '';
					$biz_status = $biz_data['orderStatus'] ?? '';

					if ($biz_status == 'PAID' && !empty($merchant_trade_no)) {
						//find the pending payment
						$sql = "select payment_uuid from v_billing_payments ";
						$sql .= "where (transaction_id = :merchant_trade_no or gateway_response_json like :like_trade_no) ";
						$sql .= "and status = 'pending' ";
						$sql .= "order by add_date desc limit 1 ";
						$parameters['merchant_trade_no'] = $merchant_trade_no;
						$parameters['like_trade_no'] = '%'.$merchant_trade_no.'%';
						$database = new database;
						$payment_uuid = $database->select($sql, $parameters, 'column');
						unset($sql, $parameters);

						if (is_uuid($payment_uuid)) {
							$billing->complete_payment($payment_uuid, $transaction_id, $biz_data);
						}
					}
				}

				http_response_code(200);
				echo json_encode(['returnCode' => 'SUCCESS', 'returnMessage' => null]);
			}
			else {
				http_response_code(400);
				echo json_encode(['returnCode' => 'FAIL', 'returnMessage' => 'Invalid signature']);
			}
			break;

		default:
			http_response_code(400);
			echo json_encode(['error' => 'Unknown gateway']);
			break;
	}

?>
