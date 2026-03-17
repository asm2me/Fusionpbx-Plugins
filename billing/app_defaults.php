<?php

//check the permission
	if (defined('STDIN')) {
		//running from command line
	}
	else {
		//access denied
		if (!permission_exists('billing_dashboard_view')) {
			//echo "access denied";
			//exit;
		}
	}

//add the default notice templates (only after schema is created)
	try {
		$sql = "select count(*) as num_rows from v_billing_notice_templates ";
		$database = new database;
		$num_rows = $database->select($sql, null, 'column');
		unset($sql);
	}
	catch (Exception $e) {
		//table does not exist yet; skip seeding defaults until schema is created
		$num_rows = false;
	}

	if ($num_rows !== false && $num_rows == 0) {
		//default variables for all templates
		$default_variables = json_encode([
			'{{domain_name}}' => 'Domain name',
			'{{plan_name}}' => 'Subscription plan name',
			'{{end_date}}' => 'Subscription end date',
			'{{days_remaining}}' => 'Days until expiration',
			'{{amount}}' => 'Invoice amount',
			'{{currency}}' => 'Currency code',
			'{{invoice_number}}' => 'Invoice number',
			'{{payment_url}}' => 'Payment URL',
			'{{company_name}}' => 'Company name',
		]);

		//30-day expiry warning
		$template_uuid = uuid();
		$array['billing_notice_templates'][0]['template_uuid'] = $template_uuid;
		$array['billing_notice_templates'][0]['template_name'] = '30-Day Expiry Warning';
		$array['billing_notice_templates'][0]['notice_type'] = 'expiry_warning_30';
		$array['billing_notice_templates'][0]['subject'] = 'Your subscription expires in 30 days - {{domain_name}}';
		$array['billing_notice_templates'][0]['body_html'] = '<h2>Subscription Expiry Notice</h2><p>Dear Customer,</p><p>Your subscription for <strong>{{domain_name}}</strong> (Plan: {{plan_name}}) will expire on <strong>{{end_date}}</strong>.</p><p>You have <strong>{{days_remaining}} days</strong> remaining. Please renew your subscription to avoid service interruption.</p><p><a href="{{payment_url}}" style="background-color:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">Renew Now</a></p><p>Thank you,<br>{{company_name}}</p>';
		$array['billing_notice_templates'][0]['body_text'] = "Subscription Expiry Notice\n\nDear Customer,\n\nYour subscription for {{domain_name}} (Plan: {{plan_name}}) will expire on {{end_date}}.\n\nYou have {{days_remaining}} days remaining. Please renew your subscription to avoid service interruption.\n\nRenew at: {{payment_url}}\n\nThank you,\n{{company_name}}";
		$array['billing_notice_templates'][0]['variables_json'] = $default_variables;
		$array['billing_notice_templates'][0]['enabled'] = 'true';
		$array['billing_notice_templates'][0]['add_date'] = date('Y-m-d H:i:s');

		//7-day expiry warning
		$template_uuid = uuid();
		$array['billing_notice_templates'][1]['template_uuid'] = $template_uuid;
		$array['billing_notice_templates'][1]['template_name'] = '7-Day Expiry Warning';
		$array['billing_notice_templates'][1]['notice_type'] = 'expiry_warning_7';
		$array['billing_notice_templates'][1]['subject'] = 'URGENT: Your subscription expires in 7 days - {{domain_name}}';
		$array['billing_notice_templates'][1]['body_html'] = '<h2>Urgent: Subscription Expiring Soon</h2><p>Dear Customer,</p><p>Your subscription for <strong>{{domain_name}}</strong> (Plan: {{plan_name}}) will expire on <strong>{{end_date}}</strong>.</p><p>You have only <strong>{{days_remaining}} days</strong> remaining. Please renew immediately to avoid service interruption.</p><p><a href="{{payment_url}}" style="background-color:#FF9800;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">Renew Now</a></p><p>Thank you,<br>{{company_name}}</p>';
		$array['billing_notice_templates'][1]['body_text'] = "URGENT: Subscription Expiring Soon\n\nDear Customer,\n\nYour subscription for {{domain_name}} (Plan: {{plan_name}}) will expire on {{end_date}}.\n\nYou have only {{days_remaining}} days remaining. Please renew immediately.\n\nRenew at: {{payment_url}}\n\nThank you,\n{{company_name}}";
		$array['billing_notice_templates'][1]['variables_json'] = $default_variables;
		$array['billing_notice_templates'][1]['enabled'] = 'true';
		$array['billing_notice_templates'][1]['add_date'] = date('Y-m-d H:i:s');

		//1-day expiry warning
		$template_uuid = uuid();
		$array['billing_notice_templates'][2]['template_uuid'] = $template_uuid;
		$array['billing_notice_templates'][2]['template_name'] = '1-Day Expiry Warning';
		$array['billing_notice_templates'][2]['notice_type'] = 'expiry_warning_1';
		$array['billing_notice_templates'][2]['subject'] = 'FINAL WARNING: Your subscription expires tomorrow - {{domain_name}}';
		$array['billing_notice_templates'][2]['body_html'] = '<h2>Final Warning: Subscription Expires Tomorrow</h2><p>Dear Customer,</p><p>Your subscription for <strong>{{domain_name}}</strong> (Plan: {{plan_name}}) will expire <strong>tomorrow ({{end_date}})</strong>.</p><p>Please renew now to avoid losing access to your services.</p><p><a href="{{payment_url}}" style="background-color:#f44336;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">Renew Now</a></p><p>Thank you,<br>{{company_name}}</p>';
		$array['billing_notice_templates'][2]['body_text'] = "FINAL WARNING: Subscription Expires Tomorrow\n\nDear Customer,\n\nYour subscription for {{domain_name}} (Plan: {{plan_name}}) will expire tomorrow ({{end_date}}).\n\nPlease renew now to avoid losing access.\n\nRenew at: {{payment_url}}\n\nThank you,\n{{company_name}}";
		$array['billing_notice_templates'][2]['variables_json'] = $default_variables;
		$array['billing_notice_templates'][2]['enabled'] = 'true';
		$array['billing_notice_templates'][2]['add_date'] = date('Y-m-d H:i:s');

		//expired notice
		$template_uuid = uuid();
		$array['billing_notice_templates'][3]['template_uuid'] = $template_uuid;
		$array['billing_notice_templates'][3]['template_name'] = 'Subscription Expired';
		$array['billing_notice_templates'][3]['notice_type'] = 'expired';
		$array['billing_notice_templates'][3]['subject'] = 'Your subscription has expired - {{domain_name}}';
		$array['billing_notice_templates'][3]['body_html'] = '<h2>Subscription Expired</h2><p>Dear Customer,</p><p>Your subscription for <strong>{{domain_name}}</strong> (Plan: {{plan_name}}) has expired on <strong>{{end_date}}</strong>.</p><p>Your service will be suspended if payment is not received within the grace period.</p><p><a href="{{payment_url}}" style="background-color:#f44336;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">Pay Now</a></p><p>Thank you,<br>{{company_name}}</p>';
		$array['billing_notice_templates'][3]['body_text'] = "Subscription Expired\n\nDear Customer,\n\nYour subscription for {{domain_name}} (Plan: {{plan_name}}) has expired on {{end_date}}.\n\nYour service will be suspended if payment is not received within the grace period.\n\nPay at: {{payment_url}}\n\nThank you,\n{{company_name}}";
		$array['billing_notice_templates'][3]['variables_json'] = $default_variables;
		$array['billing_notice_templates'][3]['enabled'] = 'true';
		$array['billing_notice_templates'][3]['add_date'] = date('Y-m-d H:i:s');

		//suspended notice
		$template_uuid = uuid();
		$array['billing_notice_templates'][4]['template_uuid'] = $template_uuid;
		$array['billing_notice_templates'][4]['template_name'] = 'Service Suspended';
		$array['billing_notice_templates'][4]['notice_type'] = 'suspended';
		$array['billing_notice_templates'][4]['subject'] = 'Your service has been suspended - {{domain_name}}';
		$array['billing_notice_templates'][4]['body_html'] = '<h2>Service Suspended</h2><p>Dear Customer,</p><p>Your service for <strong>{{domain_name}}</strong> has been suspended due to non-payment.</p><p>To restore your service, please make payment immediately.</p><p><a href="{{payment_url}}" style="background-color:#f44336;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">Pay Now to Restore</a></p><p>Thank you,<br>{{company_name}}</p>';
		$array['billing_notice_templates'][4]['body_text'] = "Service Suspended\n\nDear Customer,\n\nYour service for {{domain_name}} has been suspended due to non-payment.\n\nTo restore your service, please make payment immediately.\n\nPay at: {{payment_url}}\n\nThank you,\n{{company_name}}";
		$array['billing_notice_templates'][4]['variables_json'] = $default_variables;
		$array['billing_notice_templates'][4]['enabled'] = 'true';
		$array['billing_notice_templates'][4]['add_date'] = date('Y-m-d H:i:s');

		//payment failed notice
		$template_uuid = uuid();
		$array['billing_notice_templates'][5]['template_uuid'] = $template_uuid;
		$array['billing_notice_templates'][5]['template_name'] = 'Payment Failed';
		$array['billing_notice_templates'][5]['notice_type'] = 'payment_failed';
		$array['billing_notice_templates'][5]['subject'] = 'Payment failed for your subscription - {{domain_name}}';
		$array['billing_notice_templates'][5]['body_html'] = '<h2>Payment Failed</h2><p>Dear Customer,</p><p>We were unable to process your payment of <strong>{{amount}} {{currency}}</strong> for <strong>{{domain_name}}</strong> (Invoice: {{invoice_number}}).</p><p>Please update your payment method and try again.</p><p><a href="{{payment_url}}" style="background-color:#FF9800;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">Retry Payment</a></p><p>Thank you,<br>{{company_name}}</p>';
		$array['billing_notice_templates'][5]['body_text'] = "Payment Failed\n\nDear Customer,\n\nWe were unable to process your payment of {{amount}} {{currency}} for {{domain_name}} (Invoice: {{invoice_number}}).\n\nPlease update your payment method and try again.\n\nPay at: {{payment_url}}\n\nThank you,\n{{company_name}}";
		$array['billing_notice_templates'][5]['variables_json'] = $default_variables;
		$array['billing_notice_templates'][5]['enabled'] = 'true';
		$array['billing_notice_templates'][5]['add_date'] = date('Y-m-d H:i:s');

		//save to database
		$p = new permissions;
		$p->add('billing_notice_template_add', 'temp');
		$database = new database;
		$database->app_name = 'billing';
		$database->app_uuid = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
		$database->save($array);
		unset($array);
		$p->delete('billing_notice_template_add', 'temp');
	}

?>
