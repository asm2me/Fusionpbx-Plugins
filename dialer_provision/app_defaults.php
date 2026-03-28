<?php

if ($domains_processed == 1) {

	//remove stale menu items (wrong parent or old UUIDs from previous installs)
	$database = new database;
	$sql = "DELETE FROM v_menu_items
	        WHERE menu_item_link = '/app/dialer_provision/dialer_provision.php'
	          AND menu_item_uuid != '7b2e8f4a-3c9d-4e1b-a6f8-5d0c7a2e3b1f'";
	$database->execute($sql);
	unset($sql);

}

?>
