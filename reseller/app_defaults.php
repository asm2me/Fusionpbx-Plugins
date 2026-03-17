<?php

if ($domains_processed == 1) {

	//ensure reseller group exists
		$sql = "select count(*) as num from v_groups where group_name = 'reseller' ";
		$database = new database;
		$row = $database->select($sql, null, 'row');
		if (is_array($row) && $row['num'] == 0) {
			$array['groups'][0]['group_uuid'] = uuid();
			$array['groups'][0]['group_name'] = 'reseller';
			$array['groups'][0]['group_description'] = 'Reseller group for managing domains within allocated limits.';

			$p = new permissions;
			$p->add('group_add', 'temp');

			$database = new database;
			$database->app_name = 'reseller';
			$database->app_uuid = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
			$database->save($array);
			unset($array);

			$p->delete('group_add', 'temp');
		}
}

?>
