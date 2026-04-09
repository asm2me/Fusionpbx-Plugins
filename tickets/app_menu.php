<?php

/*
	FusionPBX - Support Tickets
	Menu definition for the default menu restore process.
*/

	$y = 0;
	$apps[$x]['menu'][$y]['title']['en-us'] = "Support Tickets";
	$apps[$x]['menu'][$y]['uuid'] = "a1b2c3d4-a001-0001-0001-ef1234567890";
	$apps[$x]['menu'][$y]['parent_uuid'] = null;
	$apps[$x]['menu'][$y]['category'] = "internal";
	$apps[$x]['menu'][$y]['icon'] = "fa-solid fa-ticket";
	$apps[$x]['menu'][$y]['path'] = "/app/tickets/tickets.php";
	$apps[$x]['menu'][$y]['order'] = "";
	$apps[$x]['menu'][$y]['groups'][] = "superadmin";
	$apps[$x]['menu'][$y]['groups'][] = "admin";
	$apps[$x]['menu'][$y]['groups'][] = "user";

?>
