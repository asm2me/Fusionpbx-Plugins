<?php

	$y = 0;
	$apps[$x]['permissions'][$y]['name'] = 'dialer_provision_view';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$apps[$x]['permissions'][$y]['groups'][] = 'admin';
	$y++;
	$apps[$x]['permissions'][$y]['name'] = 'dialer_provision_generate';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$apps[$x]['permissions'][$y]['groups'][] = 'admin';

?>
