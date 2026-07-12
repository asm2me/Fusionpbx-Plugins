<?php

/*
	FusionPBX - ERPNext / Frappe Integration
	Menu definition for the default menu restore process.
*/

	$y = 0;
	$apps[$x]['menu'][$y]['title']['en-us'] = "ERPNext Integration";
	$apps[$x]['menu'][$y]['uuid'] = "d7f1a2b3-a001-0001-0001-1b2c3d4e5f60";
	$apps[$x]['menu'][$y]['parent_uuid'] = null;
	$apps[$x]['menu'][$y]['category'] = "internal";
	$apps[$x]['menu'][$y]['icon'] = "fa-solid fa-plug";
	$apps[$x]['menu'][$y]['path'] = "/app/erpnext_integration/erpnext_integration.php";
	$apps[$x]['menu'][$y]['order'] = "";
	$apps[$x]['menu'][$y]['groups'][] = "superadmin";
	$apps[$x]['menu'][$y]['groups'][] = "admin";

?>
