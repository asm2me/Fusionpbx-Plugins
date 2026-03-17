<?php

/**
 * Billing Cron Job
 *
 * Run this script periodically (recommended: once daily) to:
 * - Check expiring subscriptions and send notices
 * - Suspend expired domains past grace period
 * - Generate recurring invoices
 * - Mark overdue invoices
 * - Auto-retry failed payments
 *
 * Usage: php /path/to/billing_cron.php
 * Crontab: 0 2 * * * php /var/www/fusionpbx/app/billing/billing_cron.php
 */

//set the include path
	$conf = glob("{/usr/local/etc/fusionpbx,/etc/fusionpbx}/config.conf", GLOB_BRACE);
	set_include_path(parse_ini_file($conf[0])['document.path']);

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";

//increase time limit for cron execution
	set_time_limit(300);
	ini_set('max_execution_time', 300);

//logging
	function billing_cron_log($message) {
		$timestamp = date('Y-m-d H:i:s');
		echo "[".$timestamp."] ".$message."\n";
		error_log("[billing_cron] ".$message);
	}

	billing_cron_log("Starting billing cron job...");

//load billing class
	require_once __DIR__ . "/resources/classes/billing.php";
	$billing = new billing;

//step 1: check expirations and send notices
	billing_cron_log("Checking expirations and sending notices...");
	$summary = $billing->check_expirations();
	billing_cron_log("Notices sent: ".$summary['notices_sent']);
	billing_cron_log("Domains suspended: ".$summary['domains_suspended']);
	billing_cron_log("Invoices generated: ".$summary['invoices_generated']);
	billing_cron_log("Subscriptions expired: ".$summary['subscriptions_expired']);

//step 2: mark overdue invoices
	billing_cron_log("Marking overdue invoices...");
	$sql = "update v_billing_invoices ";
	$sql .= "set status = 'overdue' ";
	$sql .= "where status = 'pending' ";
	$sql .= "and due_date < :now ";
	$parameters['now'] = date('Y-m-d H:i:s');
	$database = new database;
	$database->execute($sql, $parameters);
	unset($sql, $parameters);

	$sql = "select count(*) from v_billing_invoices where status = 'overdue' ";
	$database = new database;
	$overdue_count = $database->select($sql, null, 'column');
	billing_cron_log("Total overdue invoices: ".$overdue_count);

//step 3: generate recurring invoices for auto-renew subscriptions
	billing_cron_log("Generating recurring invoices...");
	$sql = "select s.subscription_uuid, s.domain_uuid, s.next_billing_date, d.domain_name ";
	$sql .= "from v_billing_subscriptions as s ";
	$sql .= "left join v_domains as d on s.domain_uuid = d.domain_uuid ";
	$sql .= "where s.status = 'active' ";
	$sql .= "and s.auto_renew = 'true' ";
	$sql .= "and s.next_billing_date <= :billing_window ";
	$sql .= "and s.subscription_uuid not in ( ";
	$sql .= "  select subscription_uuid from v_billing_invoices ";
	$sql .= "  where status in ('pending', 'paid') ";
	$sql .= "  and add_date >= :recent_date ";
	$sql .= ") ";
	$parameters['billing_window'] = date('Y-m-d H:i:s', strtotime('+7 days'));
	$parameters['recent_date'] = date('Y-m-d H:i:s', strtotime('-25 days'));
	$database = new database;
	$auto_renew = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

	$invoices_generated = 0;
	if (is_array($auto_renew)) {
		foreach ($auto_renew as $sub) {
			$invoice_uuid = $billing->generate_invoice($sub['subscription_uuid']);
			if ($invoice_uuid) {
				$invoices_generated++;
				billing_cron_log("Generated invoice for domain: ".$sub['domain_name']);
			}
		}
	}
	billing_cron_log("Recurring invoices generated: ".$invoices_generated);

//step 4: auto-retry failed payments (one retry per day, max 3 retries)
	billing_cron_log("Retrying failed payments...");
	$sql = "select p.payment_uuid, p.invoice_uuid, p.payment_gateway, p.amount, p.currency, ";
	$sql .= "p.gateway_response_json, i.domain_uuid, ";
	$sql .= "(select count(*) from v_billing_payments p2 where p2.invoice_uuid = p.invoice_uuid and p2.status = 'failed') as retry_count ";
	$sql .= "from v_billing_payments as p ";
	$sql .= "left join v_billing_invoices as i on p.invoice_uuid = i.invoice_uuid ";
	$sql .= "where p.status = 'failed' ";
	$sql .= "and p.add_date >= :retry_window ";
	$sql .= "and not exists ( ";
	$sql .= "  select 1 from v_billing_payments p3 ";
	$sql .= "  where p3.invoice_uuid = p.invoice_uuid ";
	$sql .= "  and p3.status = 'completed' ";
	$sql .= ") ";
	$sql .= "and not exists ( ";
	$sql .= "  select 1 from v_billing_payments p4 ";
	$sql .= "  where p4.invoice_uuid = p.invoice_uuid ";
	$sql .= "  and p4.add_date::date = :today ";
	$sql .= ") ";
	$parameters['retry_window'] = date('Y-m-d H:i:s', strtotime('-7 days'));
	$parameters['today'] = date('Y-m-d');
	$database = new database;
	$failed_payments = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

	$retries = 0;
	if (is_array($failed_payments)) {
		foreach ($failed_payments as $fp) {
			if ($fp['retry_count'] >= 3) {
				continue; //max retries reached
			}
			$result = $billing->process_payment($fp['invoice_uuid'], $fp['payment_gateway'], []);
			if ($result) {
				$retries++;
				billing_cron_log("Retried payment for invoice: ".$fp['invoice_uuid']);
			}
		}
	}
	billing_cron_log("Payment retries attempted: ".$retries);

//step 5: clean up old pending payments (mark as failed after 24 hours)
	billing_cron_log("Cleaning up stale pending payments...");
	$sql = "update v_billing_payments ";
	$sql .= "set status = 'failed' ";
	$sql .= "where status = 'pending' ";
	$sql .= "and add_date < :stale_date ";
	$parameters['stale_date'] = date('Y-m-d H:i:s', strtotime('-24 hours'));
	$database = new database;
	$database->execute($sql, $parameters);
	unset($sql, $parameters);

//done
	billing_cron_log("Billing cron job completed.");

?>
