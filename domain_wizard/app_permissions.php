<?php

	$y = 0;
	$apps[$x]['permissions'][$y]['name'] = 'domain_wizard_view';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$y++;
	$apps[$x]['permissions'][$y]['name'] = 'domain_wizard_add';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$y++;
	$apps[$x]['permissions'][$y]['name'] = 'domain_wizard_edit';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$y++;
	$apps[$x]['permissions'][$y]['name'] = 'domain_wizard_delete';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';

?>
