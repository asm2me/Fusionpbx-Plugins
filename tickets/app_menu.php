<?php

/*
	FusionPBX - Support Tickets Menu
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1
*/

	$y = 0;
	$apps[$x]['menu'][$y]['title']['en-us'] = "Support Tickets";
	$apps[$x]['menu'][$y]['uuid'] = "a1b2c3d4-menu-0001-0001-ef1234567890";
	$apps[$x]['menu'][$y]['parent_uuid'] = "b4750c3f-2a86-b00d-b7d0-345c14eca286"; //applications menu
	$apps[$x]['menu'][$y]['category'] = "internal";
	$apps[$x]['menu'][$y]['icon'] = "fa-solid fa-ticket";
	$apps[$x]['menu'][$y]['path'] = "/app/tickets/tickets.php";
	$apps[$x]['menu'][$y]['order'] = "";
	$apps[$x]['menu'][$y]['groups'][] = "superadmin";
	$apps[$x]['menu'][$y]['groups'][] = "admin";
	$apps[$x]['menu'][$y]['groups'][] = "user";

?>
