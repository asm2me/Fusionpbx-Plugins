<?php

	//billing dashboard
	$apps[$x]['permissions'][] = array('name' => 'billing_dashboard_view', 'groups' => array('superadmin'));

	//billing plans
	$apps[$x]['permissions'][] = array('name' => 'billing_plan_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_plan_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_plan_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_plan_delete', 'groups' => array('superadmin'));

	//billing subscriptions
	$apps[$x]['permissions'][] = array('name' => 'billing_subscription_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_subscription_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_subscription_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_subscription_delete', 'groups' => array('superadmin'));

	//billing invoices
	$apps[$x]['permissions'][] = array('name' => 'billing_invoice_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_invoice_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_invoice_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_invoice_delete', 'groups' => array('superadmin'));

	//billing payments
	$apps[$x]['permissions'][] = array('name' => 'billing_payment_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_payment_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_payment_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_payment_delete', 'groups' => array('superadmin'));

	//billing notice templates
	$apps[$x]['permissions'][] = array('name' => 'billing_notice_template_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_notice_template_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_notice_template_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_notice_template_delete', 'groups' => array('superadmin'));

	//billing payment gateways
	$apps[$x]['permissions'][] = array('name' => 'billing_gateway_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_gateway_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_gateway_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_gateway_delete', 'groups' => array('superadmin'));

	//billing credits
	$apps[$x]['permissions'][] = array('name' => 'billing_credit_view', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_credit_add', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_credit_edit', 'groups' => array('superadmin'));
	$apps[$x]['permissions'][] = array('name' => 'billing_credit_delete', 'groups' => array('superadmin'));

	//billing pay (for domain admins)
	$apps[$x]['permissions'][] = array('name' => 'billing_pay_view', 'groups' => array('superadmin', 'admin'));

?>
