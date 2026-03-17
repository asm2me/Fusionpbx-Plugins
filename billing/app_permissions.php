<?php

	//billing dashboard
	$apps[$x]['permissions'][] = array('name' => 'billing_dashboard_view', 'groups' => array('superadmin'));

	//billing plans
	$apps[$x]['permissions'][] = array('name' => 'billing_plan_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_plans_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_plans_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_plans_delete', 'groups' => array('superadmin'));

	//billing subscriptions
	$apps[$x]['permissions'][] = array('name' => 'billing_subscription_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_subscriptions_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_subscriptions_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_subscriptions_delete', 'groups' => array('superadmin'));

	//billing invoices
	$apps[$x]['permissions'][] = array('name' => 'billing_invoice_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_invoices_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_invoices_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_invoice_delete', 'groups' => array('superadmin'));

	//billing payments
	$apps[$x]['permissions'][] = array('name' => 'billing_payment_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_payments_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_payments_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_payment_delete', 'groups' => array('superadmin'));

	//billing notice templates
	$apps[$x]['permissions'][] = array('name' => 'billing_notice_template_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_notice_templates_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_notice_templates_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_notice_templates_delete', 'groups' => array('superadmin'));

	//billing payment gateways
	$apps[$x]['permissions'][] = array('name' => 'billing_gateway_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_payment_gateways_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_payment_gateways_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_payment_gateways_delete', 'groups' => array('superadmin'));

	//billing credits
	$apps[$x]['permissions'][] = array('name' => 'billing_credit_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_credits_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_credit_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_credit_delete', 'groups' => array('superadmin'));

	//billing pay (for domain admins)
	$apps[$x]['permissions'][] = array('name' => 'billing_pay_view', 'groups' => array('superadmin', 'admin'));

?>
