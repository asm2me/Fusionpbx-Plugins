<?php

/**
 * billing class
 *
 * Main billing system class for managing subscriptions, invoices,
 * payments, and domain lifecycle.
 */
class billing {

	/**
	 * Get the active subscription for a domain
	 * @param string $domain_uuid
	 * @return array|null
	 */
	public function get_subscription($domain_uuid) {
		if (!is_uuid($domain_uuid)) { return null; }

		$sql = "select s.*, p.plan_name, p.price, p.currency, p.billing_cycle, ";
		$sql .= "p.max_extensions, p.max_gateways, p.max_ivrs, p.max_call_recordings, p.max_ring_groups ";
		$sql .= "from v_billing_subscriptions as s ";
		$sql .= "left join v_billing_plans as p on s.plan_uuid = p.plan_uuid ";
		$sql .= "where s.domain_uuid = :domain_uuid ";
		$sql .= "and s.status = 'active' ";
		$sql .= "order by s.add_date desc limit 1 ";
		$parameters['domain_uuid'] = $domain_uuid;
		$database = new database;
		$result = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		return is_array($result) ? $result : null;
	}

	/**
	 * Create a new subscription for a domain
	 * @param string $domain_uuid
	 * @param string $plan_uuid
	 * @param array $options
	 * @return string|false subscription_uuid or false
	 */
	public function create_subscription($domain_uuid, $plan_uuid, $options = []) {
		if (!is_uuid($domain_uuid) || !is_uuid($plan_uuid)) { return false; }

		//get the plan details
		$sql = "select * from v_billing_plans where plan_uuid = :plan_uuid and enabled = 'true' ";
		$parameters['plan_uuid'] = $plan_uuid;
		$database = new database;
		$plan = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (!is_array($plan)) { return false; }

		//calculate dates
		$start_date = $options['start_date'] ?? date('Y-m-d H:i:s');
		$billing_cycle = $plan['billing_cycle'];

		switch ($billing_cycle) {
			case 'monthly':
				$end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +1 month'));
				break;
			case 'quarterly':
				$end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +3 months'));
				break;
			case 'yearly':
				$end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +1 year'));
				break;
			default:
				$end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +1 month'));
		}

		$subscription_uuid = uuid();

		$array['v_billing_subscriptions'][0]['subscription_uuid'] = $subscription_uuid;
		$array['v_billing_subscriptions'][0]['domain_uuid'] = $domain_uuid;
		$array['v_billing_subscriptions'][0]['plan_uuid'] = $plan_uuid;
		$array['v_billing_subscriptions'][0]['reseller_uuid'] = $options['reseller_uuid'] ?? null;
		$array['v_billing_subscriptions'][0]['status'] = 'active';
		$array['v_billing_subscriptions'][0]['start_date'] = $start_date;
		$array['v_billing_subscriptions'][0]['end_date'] = $end_date;
		$array['v_billing_subscriptions'][0]['next_billing_date'] = $end_date;
		$array['v_billing_subscriptions'][0]['auto_renew'] = $options['auto_renew'] ?? 'true';
		$array['v_billing_subscriptions'][0]['trial_ends_at'] = $options['trial_ends_at'] ?? null;
		$array['v_billing_subscriptions'][0]['add_date'] = date('Y-m-d H:i:s');
		$array['v_billing_subscriptions'][0]['add_user'] = $options['user_uuid'] ?? ($_SESSION['user_uuid'] ?? null);

		$p = new permissions;
		$p->add('v_billing_subscription_add', 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array);
		unset($array);
		$p->delete('v_billing_subscription_add', 'temp');

		//activate the domain
		$this->activate_domain($domain_uuid);

		return $subscription_uuid;
	}

	/**
	 * Suspend a domain by disabling it
	 * @param string $domain_uuid
	 * @return bool
	 */
	public function suspend_domain($domain_uuid) {
		if (!is_uuid($domain_uuid)) { return false; }

		$array['v_domains'][0]['domain_uuid'] = $domain_uuid;
		$array['v_domains'][0]['domain_enabled'] = 'false';

		$p = new permissions;
		$p->add('v_domain_edit', 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array);
		unset($array);
		$p->delete('v_domain_edit', 'temp');

		//log the event
		$this->log_event('domain_suspended', $domain_uuid);

		return true;
	}

	/**
	 * Activate a domain by enabling it
	 * @param string $domain_uuid
	 * @return bool
	 */
	public function activate_domain($domain_uuid) {
		if (!is_uuid($domain_uuid)) { return false; }

		$array['v_domains'][0]['domain_uuid'] = $domain_uuid;
		$array['v_domains'][0]['domain_enabled'] = 'true';

		$p = new permissions;
		$p->add('v_domain_edit', 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array);
		unset($array);
		$p->delete('v_domain_edit', 'temp');

		//log the event
		$this->log_event('domain_activated', $domain_uuid);

		return true;
	}

	/**
	 * Generate an invoice for a subscription
	 * @param string $subscription_uuid
	 * @return string|false invoice_uuid or false
	 */
	public function generate_invoice($subscription_uuid) {
		if (!is_uuid($subscription_uuid)) { return false; }

		//get subscription and plan details
		$sql = "select s.*, p.plan_name, p.price, p.currency, p.billing_cycle ";
		$sql .= "from v_billing_subscriptions as s ";
		$sql .= "left join v_billing_plans as p on s.plan_uuid = p.plan_uuid ";
		$sql .= "where s.subscription_uuid = :subscription_uuid ";
		$parameters['subscription_uuid'] = $subscription_uuid;
		$database = new database;
		$subscription = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (!is_array($subscription)) { return false; }

		//generate invoice number
		$invoice_prefix = $_SESSION['billing']['invoice_prefix']['text'] ?? 'INV-';
		$sql = "select count(*) as cnt from v_billing_invoices ";
		$database = new database;
		$count = $database->select($sql, null, 'column');
		$invoice_number = $invoice_prefix . str_pad(($count + 1), 6, '0', STR_PAD_LEFT);

		//calculate tax
		$tax_rate = floatval($_SESSION['billing']['tax_rate']['numeric'] ?? 0);
		$amount = floatval($subscription['price']);
		$tax_amount = round($amount * ($tax_rate / 100), 2);
		$total_amount = $amount + $tax_amount;

		//due date (15 days from now)
		$due_date = date('Y-m-d H:i:s', strtotime('+15 days'));

		$invoice_uuid = uuid();

		$array['v_billing_invoices'][0]['invoice_uuid'] = $invoice_uuid;
		$array['v_billing_invoices'][0]['subscription_uuid'] = $subscription_uuid;
		$array['v_billing_invoices'][0]['domain_uuid'] = $subscription['domain_uuid'];
		$array['v_billing_invoices'][0]['invoice_number'] = $invoice_number;
		$array['v_billing_invoices'][0]['amount'] = $amount;
		$array['v_billing_invoices'][0]['currency'] = $subscription['currency'];
		$array['v_billing_invoices'][0]['tax_amount'] = $tax_amount;
		$array['v_billing_invoices'][0]['total_amount'] = $total_amount;
		$array['v_billing_invoices'][0]['status'] = 'pending';
		$array['v_billing_invoices'][0]['due_date'] = $due_date;
		$array['v_billing_invoices'][0]['add_date'] = date('Y-m-d H:i:s');

		$p = new permissions;
		$p->add('v_billing_invoice_add', 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array);
		unset($array);
		$p->delete('v_billing_invoice_add', 'temp');

		return $invoice_uuid;
	}

	/**
	 * Process a payment for an invoice
	 * @param string $invoice_uuid
	 * @param string $gateway_name
	 * @param array $payment_data
	 * @return array|false
	 */
	public function process_payment($invoice_uuid, $gateway_name, $payment_data = []) {
		if (!is_uuid($invoice_uuid)) { return false; }

		//get invoice
		$sql = "select * from v_billing_invoices where invoice_uuid = :invoice_uuid ";
		$parameters['invoice_uuid'] = $invoice_uuid;
		$database = new database;
		$invoice = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (!is_array($invoice)) { return false; }

		//get gateway configuration
		$sql = "select * from v_billing_payment_gateways where gateway_name = :gateway_name and enabled = 'true' ";
		$parameters['gateway_name'] = $gateway_name;
		$database = new database;
		$gateway = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (!is_array($gateway)) { return false; }

		$config = json_decode($gateway['config_json'], true) ?? [];

		//initiate payment based on gateway
		$result = false;
		switch ($gateway_name) {
			case 'paypal':
				require_once __DIR__ . "/billing_paypal.php";
				$paypal = new billing_paypal;
				$paypal->set_config($config, $gateway['sandbox_mode'] == 'true');
				$result = $paypal->create_order(
					$invoice['total_amount'],
					$invoice['currency'],
					'Invoice '.$invoice['invoice_number']
				);
				break;

			case 'stripe':
				require_once __DIR__ . "/billing_stripe.php";
				$stripe = new billing_stripe;
				$stripe->set_config($config);
				$base_url = ($_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
				$result = $stripe->create_checkout_session(
					$invoice['total_amount'],
					strtolower($invoice['currency']),
					'Invoice '.$invoice['invoice_number'],
					$base_url.'/app/billing/billing_pay.php?payment=success&invoice='.$invoice_uuid,
					$base_url.'/app/billing/billing_pay.php?payment=cancelled'
				);
				break;

			case 'binance':
				require_once __DIR__ . "/billing_binance.php";
				$binance = new billing_binance;
				$binance->set_config($config);
				$result = $binance->create_order(
					$invoice['total_amount'],
					$invoice['currency'],
					'Invoice '.$invoice['invoice_number']
				);
				break;
		}

		if ($result) {
			//record the payment attempt
			$payment_uuid = uuid();
			$array['v_billing_payments'][0]['payment_uuid'] = $payment_uuid;
			$array['v_billing_payments'][0]['invoice_uuid'] = $invoice_uuid;
			$array['v_billing_payments'][0]['domain_uuid'] = $invoice['domain_uuid'];
			$array['v_billing_payments'][0]['amount'] = $invoice['total_amount'];
			$array['v_billing_payments'][0]['currency'] = $invoice['currency'];
			$array['v_billing_payments'][0]['payment_gateway'] = $gateway_name;
			$array['v_billing_payments'][0]['transaction_id'] = $result['transaction_id'] ?? '';
			$array['v_billing_payments'][0]['status'] = 'pending';
			$array['v_billing_payments'][0]['gateway_response_json'] = json_encode($result);
			$array['v_billing_payments'][0]['add_date'] = date('Y-m-d H:i:s');

			$p = new permissions;
			$p->add('v_billing_payment_add', 'temp');
			$database = new database;
			$database->app_name = 'billing';
			$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
			$database->save($array);
			unset($array);
			$p->delete('v_billing_payment_add', 'temp');

			return $result;
		}

		return false;
	}

	/**
	 * Complete a payment - called after gateway confirmation
	 * @param string $payment_uuid
	 * @param string $transaction_id
	 * @param array $gateway_response
	 * @return bool
	 */
	public function complete_payment($payment_uuid, $transaction_id, $gateway_response = []) {
		if (!is_uuid($payment_uuid)) { return false; }

		//get payment details
		$sql = "select p.*, i.subscription_uuid, i.domain_uuid ";
		$sql .= "from v_billing_payments as p ";
		$sql .= "left join v_billing_invoices as i on p.invoice_uuid = i.invoice_uuid ";
		$sql .= "where p.payment_uuid = :payment_uuid ";
		$parameters['payment_uuid'] = $payment_uuid;
		$database = new database;
		$payment = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (!is_array($payment)) { return false; }

		//update payment status
		$array['v_billing_payments'][0]['payment_uuid'] = $payment_uuid;
		$array['v_billing_payments'][0]['transaction_id'] = $transaction_id;
		$array['v_billing_payments'][0]['status'] = 'completed';
		$array['v_billing_payments'][0]['gateway_response_json'] = json_encode($gateway_response);

		$p = new permissions;
		$p->add('v_billing_payment_edit', 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array);
		unset($array);
		$p->delete('v_billing_payment_edit', 'temp');

		//update invoice status
		$array2['billing_invoices'][0]['invoice_uuid'] = $payment['invoice_uuid'];
		$array2['billing_invoices'][0]['status'] = 'paid';
		$array2['billing_invoices'][0]['paid_date'] = date('Y-m-d H:i:s');
		$array2['billing_invoices'][0]['payment_method'] = $payment['payment_gateway'];
		$array2['billing_invoices'][0]['payment_reference'] = $transaction_id;

		$p = new permissions;
		$p->add('v_billing_invoice_edit', 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array2);
		unset($array2);
		$p->delete('v_billing_invoice_edit', 'temp');

		//extend subscription
		if (is_uuid($payment['subscription_uuid'])) {
			$this->extend_subscription($payment['subscription_uuid']);
		}

		//activate domain if it was suspended
		if (is_uuid($payment['domain_uuid'])) {
			$this->activate_domain($payment['domain_uuid']);
		}

		//add credit record
		$array3['billing_credits'][0]['credit_uuid'] = uuid();
		$array3['billing_credits'][0]['domain_uuid'] = $payment['domain_uuid'];
		$array3['billing_credits'][0]['amount'] = $payment['amount'];
		$array3['billing_credits'][0]['currency'] = $payment['currency'];
		$array3['billing_credits'][0]['description'] = 'Payment received - '.$transaction_id;
		$array3['billing_credits'][0]['transaction_type'] = 'credit';
		$array3['billing_credits'][0]['reference_uuid'] = $payment_uuid;
		$array3['billing_credits'][0]['add_date'] = date('Y-m-d H:i:s');

		$p = new permissions;
		$p->add('v_billing_credit_add', 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array3);
		unset($array3);
		$p->delete('v_billing_credit_add', 'temp');

		return true;
	}

	/**
	 * Extend a subscription by one billing cycle
	 * @param string $subscription_uuid
	 * @return bool
	 */
	public function extend_subscription($subscription_uuid) {
		if (!is_uuid($subscription_uuid)) { return false; }

		$sql = "select s.*, p.billing_cycle ";
		$sql .= "from v_billing_subscriptions as s ";
		$sql .= "left join v_billing_plans as p on s.plan_uuid = p.plan_uuid ";
		$sql .= "where s.subscription_uuid = :subscription_uuid ";
		$parameters['subscription_uuid'] = $subscription_uuid;
		$database = new database;
		$sub = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (!is_array($sub)) { return false; }

		$current_end = $sub['end_date'];
		$base_date = strtotime($current_end) > time() ? $current_end : date('Y-m-d H:i:s');

		switch ($sub['billing_cycle']) {
			case 'monthly':
				$new_end = date('Y-m-d H:i:s', strtotime($base_date . ' +1 month'));
				break;
			case 'quarterly':
				$new_end = date('Y-m-d H:i:s', strtotime($base_date . ' +3 months'));
				break;
			case 'yearly':
				$new_end = date('Y-m-d H:i:s', strtotime($base_date . ' +1 year'));
				break;
			default:
				$new_end = date('Y-m-d H:i:s', strtotime($base_date . ' +1 month'));
		}

		$array['v_billing_subscriptions'][0]['subscription_uuid'] = $subscription_uuid;
		$array['v_billing_subscriptions'][0]['status'] = 'active';
		$array['v_billing_subscriptions'][0]['end_date'] = $new_end;
		$array['v_billing_subscriptions'][0]['next_billing_date'] = $new_end;
		$array['v_billing_subscriptions'][0]['mod_date'] = date('Y-m-d H:i:s');

		$p = new permissions;
		$p->add('v_billing_subscription_edit', 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array);
		unset($array);
		$p->delete('v_billing_subscription_edit', 'temp');

		return true;
	}

	/**
	 * Check for expiring and expired subscriptions - cron method
	 * @return array summary of actions taken
	 */
	public function check_expirations() {
		$summary = [
			'notices_sent' => 0,
			'domains_suspended' => 0,
			'invoices_generated' => 0,
			'subscriptions_expired' => 0,
		];

		$grace_period = intval($_SESSION['billing']['grace_period_days']['numeric'] ?? 7);
		$auto_suspend = ($_SESSION['billing']['auto_suspend']['boolean'] ?? 'true') == 'true';

		//30-day warning
		$sql = "select s.subscription_uuid, s.domain_uuid, s.end_date ";
		$sql .= "from v_billing_subscriptions as s ";
		$sql .= "where s.status = 'active' ";
		$sql .= "and s.end_date between :start_30 and :end_30 ";
		$parameters['start_30'] = date('Y-m-d', strtotime('+29 days'));
		$parameters['end_30'] = date('Y-m-d', strtotime('+31 days'));
		$database = new database;
		$expiring_30 = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		if (is_array($expiring_30)) {
			foreach ($expiring_30 as $sub) {
				if ($this->send_notice($sub['subscription_uuid'], 'expiry_warning_30')) {
					$summary['notices_sent']++;
				}
			}
		}

		//7-day warning
		$sql = "select s.subscription_uuid, s.domain_uuid, s.end_date ";
		$sql .= "from v_billing_subscriptions as s ";
		$sql .= "where s.status = 'active' ";
		$sql .= "and s.end_date between :start_7 and :end_7 ";
		$parameters['start_7'] = date('Y-m-d', strtotime('+6 days'));
		$parameters['end_7'] = date('Y-m-d', strtotime('+8 days'));
		$database = new database;
		$expiring_7 = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		if (is_array($expiring_7)) {
			foreach ($expiring_7 as $sub) {
				if ($this->send_notice($sub['subscription_uuid'], 'expiry_warning_7')) {
					$summary['notices_sent']++;
				}
			}
		}

		//1-day warning
		$sql = "select s.subscription_uuid, s.domain_uuid, s.end_date ";
		$sql .= "from v_billing_subscriptions as s ";
		$sql .= "where s.status = 'active' ";
		$sql .= "and s.end_date between :start_1 and :end_1 ";
		$parameters['start_1'] = date('Y-m-d');
		$parameters['end_1'] = date('Y-m-d', strtotime('+2 days'));
		$database = new database;
		$expiring_1 = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		if (is_array($expiring_1)) {
			foreach ($expiring_1 as $sub) {
				if ($this->send_notice($sub['subscription_uuid'], 'expiry_warning_1')) {
					$summary['notices_sent']++;
				}
			}
		}

		//expired subscriptions - mark as expired
		$sql = "select s.subscription_uuid, s.domain_uuid ";
		$sql .= "from v_billing_subscriptions as s ";
		$sql .= "where s.status = 'active' ";
		$sql .= "and s.end_date < :now ";
		$parameters['now'] = date('Y-m-d H:i:s');
		$database = new database;
		$expired = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		if (is_array($expired)) {
			foreach ($expired as $sub) {
				//mark as expired
				$array['v_billing_subscriptions'][0]['subscription_uuid'] = $sub['subscription_uuid'];
				$array['v_billing_subscriptions'][0]['status'] = 'expired';
				$array['v_billing_subscriptions'][0]['mod_date'] = date('Y-m-d H:i:s');

				$p = new permissions;
				$p->add('v_billing_subscription_edit', 'temp');
				$database = new database;
				$database->app_name = 'billing';
				$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
				$database->save($array);
				unset($array);
				$p->delete('v_billing_subscription_edit', 'temp');

				$this->send_notice($sub['subscription_uuid'], 'expired');
				$summary['subscriptions_expired']++;
				$summary['notices_sent']++;

				//generate renewal invoice
				$inv_uuid = $this->generate_invoice($sub['subscription_uuid']);
				if ($inv_uuid) {
					$summary['invoices_generated']++;
				}
			}
		}

		//suspend domains past grace period
		if ($auto_suspend) {
			$sql = "select s.subscription_uuid, s.domain_uuid ";
			$sql .= "from v_billing_subscriptions as s ";
			$sql .= "where s.status = 'expired' ";
			$sql .= "and s.end_date < :grace_date ";
			$parameters['grace_date'] = date('Y-m-d H:i:s', strtotime('-'.$grace_period.' days'));
			$database = new database;
			$to_suspend = $database->select($sql, $parameters, 'all');
			unset($sql, $parameters);

			if (is_array($to_suspend)) {
				foreach ($to_suspend as $sub) {
					//update status
					$array['v_billing_subscriptions'][0]['subscription_uuid'] = $sub['subscription_uuid'];
					$array['v_billing_subscriptions'][0]['status'] = 'suspended';
					$array['v_billing_subscriptions'][0]['mod_date'] = date('Y-m-d H:i:s');

					$p = new permissions;
					$p->add('v_billing_subscription_edit', 'temp');
					$database = new database;
					$database->app_name = 'billing';
					$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
					$database->save($array);
					unset($array);
					$p->delete('v_billing_subscription_edit', 'temp');

					$this->suspend_domain($sub['domain_uuid']);
					$this->send_notice($sub['subscription_uuid'], 'suspended');
					$summary['domains_suspended']++;
					$summary['notices_sent']++;
				}
			}
		}

		return $summary;
	}

	/**
	 * Send a billing notice
	 * @param string $subscription_uuid
	 * @param string $notice_type
	 * @return bool
	 */
	public function send_notice($subscription_uuid, $notice_type) {
		if (!is_uuid($subscription_uuid)) { return false; }

		//check if notice already sent today for this subscription and type
		$sql = "select count(*) from v_billing_notices ";
		$sql .= "where subscription_uuid = :subscription_uuid ";
		$sql .= "and notice_type = :notice_type ";
		$sql .= "and sent_date::date = :today ";
		$parameters['subscription_uuid'] = $subscription_uuid;
		$parameters['notice_type'] = $notice_type;
		$parameters['today'] = date('Y-m-d');
		$database = new database;
		$already_sent = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

		if ($already_sent > 0) { return false; }

		//get subscription details
		$sql = "select s.*, p.plan_name, d.domain_name ";
		$sql .= "from v_billing_subscriptions as s ";
		$sql .= "left join v_billing_plans as p on s.plan_uuid = p.plan_uuid ";
		$sql .= "left join v_domains as d on s.domain_uuid = d.domain_uuid ";
		$sql .= "where s.subscription_uuid = :subscription_uuid ";
		$parameters['subscription_uuid'] = $subscription_uuid;
		$database = new database;
		$sub = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (!is_array($sub)) { return false; }

		//get template
		$sql = "select * from v_billing_notice_templates ";
		$sql .= "where notice_type = :notice_type and enabled = 'true' ";
		$sql .= "limit 1 ";
		$parameters['notice_type'] = $notice_type;
		$database = new database;
		$template = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (!is_array($template)) { return false; }

		//get domain admin email
		$sql = "select contact_email from v_contacts ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "limit 1 ";
		$parameters['domain_uuid'] = $sub['domain_uuid'];
		$database = new database;
		$email = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

		if (empty($email)) {
			//fallback: try to get from users
			$sql = "select user_email from v_users ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$sql .= "and user_enabled = 'true' ";
			$sql .= "order by add_date asc limit 1 ";
			$parameters['domain_uuid'] = $sub['domain_uuid'];
			$database = new database;
			$email = $database->select($sql, $parameters, 'column');
			unset($sql, $parameters);
		}

		//prepare variables
		$days_remaining = max(0, floor((strtotime($sub['end_date']) - time()) / 86400));
		$company_name = $_SESSION['billing']['company_name']['text'] ?? '';
		$base_url = ($_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

		$variables = [
			'{{domain_name}}' => $sub['domain_name'],
			'{{plan_name}}' => $sub['plan_name'],
			'{{end_date}}' => substr($sub['end_date'], 0, 10),
			'{{days_remaining}}' => $days_remaining,
			'{{amount}}' => '',
			'{{currency}}' => '',
			'{{invoice_number}}' => '',
			'{{payment_url}}' => $base_url.'/app/billing/billing_pay.php',
			'{{company_name}}' => $company_name,
		];

		$subject = str_replace(array_keys($variables), array_values($variables), $template['subject']);
		$body_html = str_replace(array_keys($variables), array_values($variables), $template['body_html']);
		$body_text = str_replace(array_keys($variables), array_values($variables), $template['body_text']);

		//send email using FusionPBX email system
		$sent = false;
		if (!empty($email)) {
			if (class_exists('email')) {
				try {
					$e = new email;
					$e->recipients = $email;
					$e->subject = $subject;
					$e->body = $body_html;
					$e->from_name = $company_name;
					$sent = $e->send();
				}
				catch (Exception $ex) {
					//log error
					error_log('Billing notice email failed: '.$ex->getMessage());
				}
			}
		}

		//record the notice
		$array['v_billing_notices'][0]['notice_uuid'] = uuid();
		$array['v_billing_notices'][0]['subscription_uuid'] = $subscription_uuid;
		$array['v_billing_notices'][0]['domain_uuid'] = $sub['domain_uuid'];
		$array['v_billing_notices'][0]['notice_type'] = $notice_type;
		$array['v_billing_notices'][0]['sent_date'] = date('Y-m-d H:i:s');
		$array['v_billing_notices'][0]['sent_to'] = $email ?? '';
		$array['v_billing_notices'][0]['sent_via'] = 'email';
		$array['v_billing_notices'][0]['template_used'] = $template['template_name'];
		$array['v_billing_notices'][0]['status'] = $sent ? 'sent' : 'failed';

		$p = new permissions;
		$p->add('v_billing_notice_template_add', 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array);
		unset($array);
		$p->delete('v_billing_notice_template_add', 'temp');

		return true;
	}

	/**
	 * Get dashboard statistics
	 * @return array
	 */
	public function get_dashboard_stats() {
		$stats = [];

		//active subscriptions
		$sql = "select count(*) from v_billing_subscriptions where status = 'active' ";
		$database = new database;
		$stats['active_subscriptions'] = $database->select($sql, null, 'column') ?? 0;
		unset($sql);

		//revenue this month
		$sql = "select coalesce(sum(amount), 0) from v_billing_payments ";
		$sql .= "where status = 'completed' ";
		$sql .= "and add_date >= :month_start ";
		$parameters['month_start'] = date('Y-m-01 00:00:00');
		$database = new database;
		$stats['revenue_this_month'] = $database->select($sql, $parameters, 'column') ?? 0;
		unset($sql, $parameters);

		//pending invoices
		$sql = "select count(*) from v_billing_invoices where status in ('pending', 'overdue') ";
		$database = new database;
		$stats['pending_invoices'] = $database->select($sql, null, 'column') ?? 0;
		unset($sql);

		//upcoming expirations (next 30 days)
		$sql = "select count(*) from v_billing_subscriptions ";
		$sql .= "where status = 'active' ";
		$sql .= "and end_date <= :expiry_date ";
		$parameters['expiry_date'] = date('Y-m-d H:i:s', strtotime('+30 days'));
		$database = new database;
		$stats['upcoming_expirations'] = $database->select($sql, $parameters, 'column') ?? 0;
		unset($sql, $parameters);

		//total revenue all time
		$sql = "select coalesce(sum(amount), 0) from v_billing_payments where status = 'completed' ";
		$database = new database;
		$stats['total_revenue'] = $database->select($sql, null, 'column') ?? 0;
		unset($sql);

		//total domains
		$sql = "select count(distinct domain_uuid) from v_billing_subscriptions ";
		$database = new database;
		$stats['total_domains'] = $database->select($sql, null, 'column') ?? 0;
		unset($sql);

		return $stats;
	}

	/**
	 * Log billing events
	 * @param string $event
	 * @param string $domain_uuid
	 * @param string $details
	 */
	private function log_event($event, $domain_uuid, $details = '') {
		//use FusionPBX logging if available
		if (function_exists('save_call_center_log') || class_exists('event_socket')) {
			//basic file logging fallback
			$log_message = date('Y-m-d H:i:s')." [billing] ".$event." domain:".$domain_uuid." ".$details;
			error_log($log_message);
		}
	}

}

?>
