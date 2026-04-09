<?php

	$apps[$x]['menu'][0]['title']['en-us'] = 'Billing';
	$apps[$x]['menu'][0]['uuid'] = 'b2c3d4e5-0003-0001-0001-000000000001';
	$apps[$x]['menu'][0]['parent_uuid'] = '594d99c5-6128-9c88-ca35-4b33392cec0f'; //advanced
	$apps[$x]['menu'][0]['category'] = 'internal';
	$apps[$x]['menu'][0]['icon'] = 'fa-solid fa-money-bill-wave';
	$apps[$x]['menu'][0]['path'] = '';
	$apps[$x]['menu'][0]['order'] = '';
	$apps[$x]['menu'][0]['groups'][] = 'superadmin';

	$apps[$x]['menu'][1]['title']['en-us'] = 'Dashboard';
	$apps[$x]['menu'][1]['uuid'] = 'b2c3d4e5-0003-0001-0001-000000000002';
	$apps[$x]['menu'][1]['parent_uuid'] = 'b2c3d4e5-0003-0001-0001-000000000001';
	$apps[$x]['menu'][1]['category'] = 'internal';
	$apps[$x]['menu'][1]['icon'] = 'fa-solid fa-gauge-high';
	$apps[$x]['menu'][1]['path'] = '/app/billing/billing.php';
	$apps[$x]['menu'][1]['order'] = '';
	$apps[$x]['menu'][1]['groups'][] = 'superadmin';

	$apps[$x]['menu'][2]['title']['en-us'] = 'Plans';
	$apps[$x]['menu'][2]['uuid'] = 'b2c3d4e5-0003-0001-0001-000000000003';
	$apps[$x]['menu'][2]['parent_uuid'] = 'b2c3d4e5-0003-0001-0001-000000000001';
	$apps[$x]['menu'][2]['category'] = 'internal';
	$apps[$x]['menu'][2]['icon'] = 'fa-solid fa-list-check';
	$apps[$x]['menu'][2]['path'] = '/app/billing/billing_plans.php';
	$apps[$x]['menu'][2]['order'] = '';
	$apps[$x]['menu'][2]['groups'][] = 'superadmin';

	$apps[$x]['menu'][3]['title']['en-us'] = 'Subscriptions';
	$apps[$x]['menu'][3]['uuid'] = 'b2c3d4e5-0003-0001-0001-000000000004';
	$apps[$x]['menu'][3]['parent_uuid'] = 'b2c3d4e5-0003-0001-0001-000000000001';
	$apps[$x]['menu'][3]['category'] = 'internal';
	$apps[$x]['menu'][3]['icon'] = 'fa-solid fa-arrows-rotate';
	$apps[$x]['menu'][3]['path'] = '/app/billing/billing_subscriptions.php';
	$apps[$x]['menu'][3]['order'] = '';
	$apps[$x]['menu'][3]['groups'][] = 'superadmin';

	$apps[$x]['menu'][4]['title']['en-us'] = 'Invoices';
	$apps[$x]['menu'][4]['uuid'] = 'b2c3d4e5-0003-0001-0001-000000000005';
	$apps[$x]['menu'][4]['parent_uuid'] = 'b2c3d4e5-0003-0001-0001-000000000001';
	$apps[$x]['menu'][4]['category'] = 'internal';
	$apps[$x]['menu'][4]['icon'] = 'fa-solid fa-file-invoice-dollar';
	$apps[$x]['menu'][4]['path'] = '/app/billing/billing_invoices.php';
	$apps[$x]['menu'][4]['order'] = '';
	$apps[$x]['menu'][4]['groups'][] = 'superadmin';

	$apps[$x]['menu'][5]['title']['en-us'] = 'Payments';
	$apps[$x]['menu'][5]['uuid'] = 'b2c3d4e5-0003-0001-0001-000000000006';
	$apps[$x]['menu'][5]['parent_uuid'] = 'b2c3d4e5-0003-0001-0001-000000000001';
	$apps[$x]['menu'][5]['category'] = 'internal';
	$apps[$x]['menu'][5]['icon'] = 'fa-solid fa-credit-card';
	$apps[$x]['menu'][5]['path'] = '/app/billing/billing_payments.php';
	$apps[$x]['menu'][5]['order'] = '';
	$apps[$x]['menu'][5]['groups'][] = 'superadmin';

	$apps[$x]['menu'][6]['title']['en-us'] = 'Notice Templates';
	$apps[$x]['menu'][6]['uuid'] = 'b2c3d4e5-0003-0001-0001-000000000007';
	$apps[$x]['menu'][6]['parent_uuid'] = 'b2c3d4e5-0003-0001-0001-000000000001';
	$apps[$x]['menu'][6]['category'] = 'internal';
	$apps[$x]['menu'][6]['icon'] = 'fa-solid fa-envelope';
	$apps[$x]['menu'][6]['path'] = '/app/billing/billing_notice_templates.php';
	$apps[$x]['menu'][6]['order'] = '';
	$apps[$x]['menu'][6]['groups'][] = 'superadmin';

	$apps[$x]['menu'][7]['title']['en-us'] = 'Payment Gateways';
	$apps[$x]['menu'][7]['uuid'] = 'b2c3d4e5-0003-0001-0001-000000000008';
	$apps[$x]['menu'][7]['parent_uuid'] = 'b2c3d4e5-0003-0001-0001-000000000001';
	$apps[$x]['menu'][7]['category'] = 'internal';
	$apps[$x]['menu'][7]['icon'] = 'fa-solid fa-gears';
	$apps[$x]['menu'][7]['path'] = '/app/billing/billing_gateways.php';
	$apps[$x]['menu'][7]['order'] = '';
	$apps[$x]['menu'][7]['groups'][] = 'superadmin';

	$apps[$x]['menu'][8]['title']['en-us'] = 'Credits';
	$apps[$x]['menu'][8]['uuid'] = 'b2c3d4e5-0003-0001-0001-000000000009';
	$apps[$x]['menu'][8]['parent_uuid'] = 'b2c3d4e5-0003-0001-0001-000000000001';
	$apps[$x]['menu'][8]['category'] = 'internal';
	$apps[$x]['menu'][8]['icon'] = 'fa-solid fa-coins';
	$apps[$x]['menu'][8]['path'] = '/app/billing/billing_credits.php';
	$apps[$x]['menu'][8]['order'] = '';
	$apps[$x]['menu'][8]['groups'][] = 'superadmin';

?>
