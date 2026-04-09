<?php

	//main reseller menu
		$y = 0;
		$apps[$x]['menu'][$y]['title']['en-us'] = 'Reseller';
		$apps[$x]['menu'][$y]['uuid'] = 'c3d40010-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['parent_uuid'] = '594d99c5-6128-9c88-ca35-4b33392cec0f'; //advanced
		$apps[$x]['menu'][$y]['category'] = 'internal';
		$apps[$x]['menu'][$y]['icon'] = 'fa-solid fa-handshake';
		$apps[$x]['menu'][$y]['path'] = '';
		$apps[$x]['menu'][$y]['order'] = '';
		$apps[$x]['menu'][$y]['groups'][] = 'superadmin';
		$apps[$x]['menu'][$y]['groups'][] = 'reseller';

	//dashboard
		$y++;
		$apps[$x]['menu'][$y]['title']['en-us'] = 'Dashboard';
		$apps[$x]['menu'][$y]['uuid'] = 'c3d40002-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['parent_uuid'] = 'c3d40010-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['category'] = 'internal';
		$apps[$x]['menu'][$y]['icon'] = 'fa-solid fa-gauge-high';
		$apps[$x]['menu'][$y]['path'] = '/app/reseller/reseller.php';
		$apps[$x]['menu'][$y]['order'] = '';
		$apps[$x]['menu'][$y]['groups'][] = 'superadmin';
		$apps[$x]['menu'][$y]['groups'][] = 'reseller';

	//my domains
		$y++;
		$apps[$x]['menu'][$y]['title']['en-us'] = 'My Domains';
		$apps[$x]['menu'][$y]['uuid'] = 'c3d40003-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['parent_uuid'] = 'c3d40010-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['category'] = 'internal';
		$apps[$x]['menu'][$y]['icon'] = 'fa-solid fa-globe';
		$apps[$x]['menu'][$y]['path'] = '/app/reseller/reseller_domains.php';
		$apps[$x]['menu'][$y]['order'] = '';
		$apps[$x]['menu'][$y]['groups'][] = 'superadmin';
		$apps[$x]['menu'][$y]['groups'][] = 'reseller';

	//create domain
		$y++;
		$apps[$x]['menu'][$y]['title']['en-us'] = 'Create Domain';
		$apps[$x]['menu'][$y]['uuid'] = 'c3d40011-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['parent_uuid'] = 'c3d40010-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['category'] = 'internal';
		$apps[$x]['menu'][$y]['icon'] = 'fa-solid fa-plus-circle';
		$apps[$x]['menu'][$y]['path'] = '/app/reseller/reseller_domain_create.php';
		$apps[$x]['menu'][$y]['order'] = '';
		$apps[$x]['menu'][$y]['groups'][] = 'superadmin';
		$apps[$x]['menu'][$y]['groups'][] = 'reseller';

	//plans
		$y++;
		$apps[$x]['menu'][$y]['title']['en-us'] = 'Plans';
		$apps[$x]['menu'][$y]['uuid'] = 'c3d40004-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['parent_uuid'] = 'c3d40010-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['category'] = 'internal';
		$apps[$x]['menu'][$y]['icon'] = 'fa-solid fa-clipboard-list';
		$apps[$x]['menu'][$y]['path'] = '/app/reseller/reseller_plans.php';
		$apps[$x]['menu'][$y]['order'] = '';
		$apps[$x]['menu'][$y]['groups'][] = 'superadmin';
		$apps[$x]['menu'][$y]['groups'][] = 'reseller';

	//commissions
		$y++;
		$apps[$x]['menu'][$y]['title']['en-us'] = 'Commissions';
		$apps[$x]['menu'][$y]['uuid'] = 'c3d40005-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['parent_uuid'] = 'c3d40010-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['category'] = 'internal';
		$apps[$x]['menu'][$y]['icon'] = 'fa-solid fa-percent';
		$apps[$x]['menu'][$y]['path'] = '/app/reseller/reseller_commissions.php';
		$apps[$x]['menu'][$y]['order'] = '';
		$apps[$x]['menu'][$y]['groups'][] = 'superadmin';
		$apps[$x]['menu'][$y]['groups'][] = 'reseller';

	//activity log
		$y++;
		$apps[$x]['menu'][$y]['title']['en-us'] = 'Activity Log';
		$apps[$x]['menu'][$y]['uuid'] = 'c3d40006-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['parent_uuid'] = 'c3d40010-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['category'] = 'internal';
		$apps[$x]['menu'][$y]['icon'] = 'fa-solid fa-clock-rotate-left';
		$apps[$x]['menu'][$y]['path'] = '/app/reseller/reseller_activity.php';
		$apps[$x]['menu'][$y]['order'] = '';
		$apps[$x]['menu'][$y]['groups'][] = 'superadmin';
		$apps[$x]['menu'][$y]['groups'][] = 'reseller';

	//settings/profile
		$y++;
		$apps[$x]['menu'][$y]['title']['en-us'] = 'Settings';
		$apps[$x]['menu'][$y]['uuid'] = 'c3d40007-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['parent_uuid'] = 'c3d40010-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['category'] = 'internal';
		$apps[$x]['menu'][$y]['icon'] = 'fa-solid fa-gear';
		$apps[$x]['menu'][$y]['path'] = '/app/reseller/reseller_settings.php';
		$apps[$x]['menu'][$y]['order'] = '';
		$apps[$x]['menu'][$y]['groups'][] = 'superadmin';
		$apps[$x]['menu'][$y]['groups'][] = 'reseller';

	//admin manage resellers (superadmin only)
		$y++;
		$apps[$x]['menu'][$y]['title']['en-us'] = 'Manage Resellers';
		$apps[$x]['menu'][$y]['uuid'] = 'c3d40001-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['parent_uuid'] = 'c3d40010-a7b8-9012-cdef-123456789012';
		$apps[$x]['menu'][$y]['category'] = 'internal';
		$apps[$x]['menu'][$y]['icon'] = 'fa-solid fa-users-gear';
		$apps[$x]['menu'][$y]['path'] = '/app/reseller/reseller.php?show=all';
		$apps[$x]['menu'][$y]['order'] = '';
		$apps[$x]['menu'][$y]['groups'][] = 'superadmin';

?>
