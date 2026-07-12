<?php

/*
	FusionPBX - ERPNext / Frappe Integration
	Permission definitions for the default permission restore process.
*/

	$y = 0;
	$apps[$x]['permissions'][$y]['name'] = "erpnext_integration_view";
	$apps[$x]['permissions'][$y]['menu']['uuid'] = "d7f1a2b3-a001-0001-0001-1b2c3d4e5f60";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$y++;

	$apps[$x]['permissions'][$y]['name'] = "erpnext_integration_edit";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$y++;

	$apps[$x]['permissions'][$y]['name'] = "erpnext_integration_api";
	$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
	$apps[$x]['permissions'][$y]['groups'][] = "admin";
	$apps[$x]['permissions'][$y]['groups'][] = "user";

?>
