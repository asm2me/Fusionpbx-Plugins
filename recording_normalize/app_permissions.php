<?php

	$y = 0;
	$apps[$x]['permissions'][$y]['name'] = 'recording_normalize_view';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$apps[$x]['permissions'][$y]['groups'][] = 'admin';
	$y++;
	$apps[$x]['permissions'][$y]['name'] = 'recording_normalize_edit';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$apps[$x]['permissions'][$y]['groups'][] = 'admin';

?>
