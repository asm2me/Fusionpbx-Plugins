<?php

/**
 * domain_wizard
 *
 * Domain Creation Wizard class for FusionPBX.
 * Clones a source domain with configurable options including extensions,
 * gateways, IVRs, ring groups, and recordings.
 */
class domain_wizard {

	/**
	 * Internal log buffer
	 */
	private $log = [];

	/**
	 * Clone a domain from a source domain
	 * @param string $source_uuid  Source domain UUID
	 * @param string $new_domain_name  New domain name
	 * @param array  $options  Configuration options
	 * @return array  Result with status, message, domain_uuid, and log
	 */
	public function clone_domain($source_uuid, $new_domain_name, $options = []) {
		$this->log = [];
		$this->log[] = 'Starting domain clone process...';

		//validate source domain exists
			$sql = "select * from v_domains where domain_uuid = :domain_uuid";
			$parameters['domain_uuid'] = $source_uuid;
			$database = new database;
			$source_domain = $database->select($sql, $parameters, 'row');
			unset($sql, $parameters);

			if (!is_array($source_domain)) {
				return ['status' => 'error', 'message' => 'Source domain not found.', 'log' => $this->log];
			}
			$this->log[] = 'Source domain found: ' . $source_domain['domain_name'];

		//create the new domain using direct SQL for reliability (works without session)
			$new_domain_uuid = uuid();

			$sql = "insert into v_domains (domain_uuid, domain_name, domain_enabled, domain_description) ";
			$sql .= "values (:domain_uuid, :domain_name, :domain_enabled, :domain_description) ";
			$parameters['domain_uuid'] = $new_domain_uuid;
			$parameters['domain_name'] = $new_domain_name;
			$parameters['domain_enabled'] = 'true';
			$parameters['domain_description'] = 'Created by Domain Wizard from ' . $source_domain['domain_name'];
			$database = new database;
			$database->execute($sql, $parameters);
			unset($sql, $parameters);

		//verify the domain was actually created
			$sql = "select count(*) from v_domains where domain_uuid = :domain_uuid";
			$parameters['domain_uuid'] = $new_domain_uuid;
			$database = new database;
			$domain_exists = $database->select($sql, $parameters, 'column');
			unset($sql, $parameters);

			if (!$domain_exists) {
				$this->log[] = 'FAILED: Could not create domain in database.';
				return ['status' => 'error', 'message' => 'Could not create domain in database.', 'log' => $this->log];
			}

			$this->log[] = 'New domain created: ' . $new_domain_name . ' (' . $new_domain_uuid . ')';

		//clone extensions
			$extensions_count = (int)($options['extensions_count'] ?? 0);
			$extension_start = (int)($options['extension_start'] ?? 1000);
			if ($extensions_count > 0) {
				$ext_result = $this->clone_extensions($source_uuid, $new_domain_uuid, $extensions_count, $extension_start);
				$this->log[] = 'Extensions cloned: ' . $ext_result;
			}

		//clone gateways
			$gateways_count = (int)($options['gateways_count'] ?? 0);
			if ($gateways_count > 0) {
				$gw_result = $this->clone_gateways($source_uuid, $new_domain_uuid, $gateways_count);
				$this->log[] = 'Gateways cloned: ' . $gw_result;
			}

		//clone dialplan
			$this->clone_dialplan($source_uuid, $new_domain_uuid);
			$this->log[] = 'Dialplan entries cloned.';

		//clone IVRs
			$ivrs_count = (int)($options['ivrs_count'] ?? 0);
			if ($ivrs_count > 0) {
				$ivr_result = $this->clone_ivrs($source_uuid, $new_domain_uuid, $ivrs_count);
				$this->log[] = 'IVRs cloned: ' . $ivr_result;
			}

		//clone ring groups
			$ring_groups_count = (int)($options['ring_groups_count'] ?? 0);
			if ($ring_groups_count > 0) {
				$rg_result = $this->clone_ring_groups($source_uuid, $new_domain_uuid, $ring_groups_count);
				$this->log[] = 'Ring groups cloned: ' . $rg_result;
			}

		//upload recordings
			$recordings_uploaded = 0;
			if (isset($options['recordings']) && is_array($options['recordings']['name'])) {
				$recordings_uploaded = $this->upload_recordings($new_domain_uuid, $options['recordings']);
				$this->log[] = 'Recordings uploaded: ' . $recordings_uploaded;
			}

		//handle IVR recording uploads and custom IVR configs
			if (!empty($options['ivr_recordings']) && is_array($options['ivr_recordings'])) {
				foreach ($options['ivr_recordings'] as $index => $file) {
					$uploaded = $this->upload_single_recording($new_domain_uuid, $file);
					if ($uploaded) {
						$this->log[] = 'IVR recording uploaded: ' . $uploaded;
					}
				}
			}

		//create custom gateway if provided
			if (!empty($options['gateway_config']) && is_array($options['gateway_config'])) {
				$gw = $options['gateway_config'];
				if (!empty($gw['proxy'])) {
					$this->create_gateway($new_domain_uuid, $gw);
					$this->log[] = 'SIP gateway created: ' . ($gw['name'] ?: $gw['proxy']);
				}
			}

		//create admin user
			if (!empty($options['admin_username']) && !empty($options['admin_password'])) {
				$this->create_admin_user($new_domain_uuid, $options['admin_username'], $options['admin_password']);
				$this->log[] = 'Admin user created: ' . $options['admin_username'];
			}

		//log the action
			$this->log_action([
				'domain_uuid' => $new_domain_uuid,
				'template_uuid' => $options['template_uuid'] ?? null,
				'created_by' => $_SESSION['user_uuid'] ?? null,
				'extensions_count' => $extensions_count,
				'gateways_count' => $gateways_count,
				'ivrs_count' => $ivrs_count,
				'recordings_uploaded' => $recordings_uploaded,
				'status' => 'success',
				'log_detail' => implode("\n", $this->log),
			]);

		$this->log[] = 'Domain clone process completed successfully.';

		return [
			'status' => 'success',
			'message' => 'Domain created successfully.',
			'domain_uuid' => $new_domain_uuid,
			'log' => $this->log,
		];
	}

	/**
	 * Clone extensions from source domain
	 * @param string $source_uuid   Source domain UUID
	 * @param string $target_uuid   Target domain UUID
	 * @param int    $count         Number of extensions to create
	 * @param int    $start_number  Starting extension number
	 * @return int   Number of extensions created
	 */
	public function clone_extensions($source_uuid, $target_uuid, $count, $start_number = 1000) {
		//get source extension as template
			$sql = "select * from v_extensions where domain_uuid = :domain_uuid order by extension asc limit 1";
			$parameters['domain_uuid'] = $source_uuid;
			$database = new database;
			$source_ext = $database->select($sql, $parameters, 'row');
			unset($sql, $parameters);

		$created = 0;
		if (is_array($source_ext)) {
			for ($i = 0; $i < $count; $i++) {
				$ext_number = $start_number + $i;
				$sql = "insert into v_extensions (extension_uuid, domain_uuid, extension, password, ";
				$sql .= "effective_caller_id_name, effective_caller_id_number, outbound_caller_id_name, outbound_caller_id_number, ";
				$sql .= "directory_first_name, directory_last_name, directory_visible, directory_exten_visible, ";
				$sql .= "max_registrations, limit_max, limit_destination, user_context, call_timeout, enabled, description) ";
				$sql .= "values (:extension_uuid, :domain_uuid, :extension, :password, ";
				$sql .= ":effective_caller_id_name, :effective_caller_id_number, :outbound_caller_id_name, :outbound_caller_id_number, ";
				$sql .= ":directory_first_name, :directory_last_name, :directory_visible, :directory_exten_visible, ";
				$sql .= ":max_registrations, :limit_max, :limit_destination, :user_context, :call_timeout, :enabled, :description) ";

				$parameters = [];
				$parameters['extension_uuid'] = uuid();
				$parameters['domain_uuid'] = $target_uuid;
				$parameters['extension'] = (string)$ext_number;
				$parameters['password'] = bin2hex(random_bytes(6));
				$parameters['effective_caller_id_name'] = 'Extension ' . $ext_number;
				$parameters['effective_caller_id_number'] = (string)$ext_number;
				$parameters['outbound_caller_id_name'] = $source_ext['outbound_caller_id_name'] ?? '';
				$parameters['outbound_caller_id_number'] = $source_ext['outbound_caller_id_number'] ?? '';
				$parameters['directory_first_name'] = 'Extension';
				$parameters['directory_last_name'] = (string)$ext_number;
				$parameters['directory_visible'] = 'true';
				$parameters['directory_exten_visible'] = 'true';
				$parameters['max_registrations'] = $source_ext['max_registrations'] ?? '1';
				$parameters['limit_max'] = $source_ext['limit_max'] ?? '5';
				$parameters['limit_destination'] = $source_ext['limit_destination'] ?? 'error/user_busy';
				$parameters['user_context'] = $target_uuid;
				$parameters['call_timeout'] = $source_ext['call_timeout'] ?? '30';
				$parameters['enabled'] = 'true';
				$parameters['description'] = 'Created by Domain Wizard';

				$database = new database;
				$database->execute($sql, $parameters);
				unset($sql, $parameters);
				$created++;
			}
		}
		return $created;
	}

	/**
	 * Clone gateways from source domain
	 * @param string $source_uuid  Source domain UUID
	 * @param string $target_uuid  Target domain UUID
	 * @param int    $count        Number of gateways to clone
	 * @return int   Number of gateways created
	 */
	public function clone_gateways($source_uuid, $target_uuid, $count) {
		$sql = "select * from v_gateways where domain_uuid = :domain_uuid order by gateway_uuid asc";
		$parameters['domain_uuid'] = $source_uuid;
		$database = new database;
		$source_gateways = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		$created = 0;
		if (is_array($source_gateways)) {
			foreach ($source_gateways as $gw) {
				if ($created >= $count) break;

				$sql = "insert into v_gateways (gateway_uuid, domain_uuid, gateway, username, password, proxy, register, ";
				$sql .= "expire_seconds, retry_seconds, context, profile, caller_id_in_from, enabled, description) ";
				$sql .= "values (:gateway_uuid, :domain_uuid, :gateway, :username, :password, :proxy, :register, ";
				$sql .= ":expire_seconds, :retry_seconds, :context, :profile, :caller_id_in_from, :enabled, :description) ";

				$parameters = [];
				$parameters['gateway_uuid'] = uuid();
				$parameters['domain_uuid'] = $target_uuid;
				$parameters['gateway'] = ($gw['gateway'] ?? '') . '_clone';
				$parameters['username'] = $gw['username'] ?? '';
				$parameters['password'] = $gw['password'] ?? '';
				$parameters['proxy'] = $gw['proxy'] ?? '';
				$parameters['register'] = $gw['register'] ?? 'false';
				$parameters['expire_seconds'] = $gw['expire_seconds'] ?? '800';
				$parameters['retry_seconds'] = $gw['retry_seconds'] ?? '30';
				$parameters['context'] = $target_uuid;
				$parameters['profile'] = $gw['profile'] ?? 'external';
				$parameters['caller_id_in_from'] = $gw['caller_id_in_from'] ?? 'false';
				$parameters['enabled'] = 'false';
				$parameters['description'] = 'Cloned by Domain Wizard';

				$database = new database;
				$database->execute($sql, $parameters);
				unset($sql, $parameters);
				$created++;
			}
		}
		return $created;
	}

	/**
	 * Clone dialplan entries from source domain
	 * @param string $source_uuid  Source domain UUID
	 * @param string $target_uuid  Target domain UUID
	 */
	private function clone_dialplan($source_uuid, $target_uuid) {
		$sql = "select * from v_dialplans where domain_uuid = :domain_uuid order by dialplan_order asc";
		$parameters['domain_uuid'] = $source_uuid;
		$database = new database;
		$source_dialplans = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		if (is_array($source_dialplans)) {
			foreach ($source_dialplans as $dp) {
				$old_dp_uuid = $dp['dialplan_uuid'];
				$new_dp_uuid = uuid();

				$sql = "insert into v_dialplans (dialplan_uuid, domain_uuid, app_uuid, dialplan_name, dialplan_number, dialplan_context, dialplan_continue, dialplan_order, dialplan_enabled, dialplan_description) ";
				$sql .= "values (:dialplan_uuid, :domain_uuid, :app_uuid, :dialplan_name, :dialplan_number, :dialplan_context, :dialplan_continue, :dialplan_order, :dialplan_enabled, :dialplan_description) ";
				$parameters = [];
				$parameters['dialplan_uuid'] = $new_dp_uuid;
				$parameters['domain_uuid'] = $target_uuid;
				$parameters['app_uuid'] = $dp['app_uuid'] ?? '';
				$parameters['dialplan_name'] = $dp['dialplan_name'];
				$parameters['dialplan_number'] = $dp['dialplan_number'] ?? '';
				$parameters['dialplan_context'] = $target_uuid;
				$parameters['dialplan_continue'] = $dp['dialplan_continue'] ?? '';
				$parameters['dialplan_order'] = $dp['dialplan_order'];
				$parameters['dialplan_enabled'] = $dp['dialplan_enabled'];
				$parameters['dialplan_description'] = $dp['dialplan_description'] ?? '';
				$database = new database;
				$database->execute($sql, $parameters);
				unset($sql, $parameters);

				//clone details
				$sql2 = "select * from v_dialplan_details where dialplan_uuid = :dialplan_uuid";
				$parameters2['dialplan_uuid'] = $old_dp_uuid;
				$database = new database;
				$details = $database->select($sql2, $parameters2, 'all');
				unset($sql2, $parameters2);

				if (is_array($details) && sizeof($details) > 0) {
					foreach ($details as $detail) {
						$sql = "insert into v_dialplan_details (dialplan_detail_uuid, dialplan_uuid, domain_uuid, dialplan_detail_tag, dialplan_detail_type, dialplan_detail_data, dialplan_detail_break, dialplan_detail_group, dialplan_detail_order, dialplan_detail_enabled) ";
						$sql .= "values (:dialplan_detail_uuid, :dialplan_uuid, :domain_uuid, :tag, :type, :data, :break, :grp, :ord, :enabled) ";
						$parameters = [];
						$parameters['dialplan_detail_uuid'] = uuid();
						$parameters['dialplan_uuid'] = $new_dp_uuid;
						$parameters['domain_uuid'] = $target_uuid;
						$parameters['tag'] = $detail['dialplan_detail_tag'];
						$parameters['type'] = $detail['dialplan_detail_type'];
						$parameters['data'] = $detail['dialplan_detail_data'];
						$parameters['break'] = $detail['dialplan_detail_break'] ?? '';
						$parameters['grp'] = $detail['dialplan_detail_group'] ?? '0';
						$parameters['ord'] = $detail['dialplan_detail_order'];
						$parameters['enabled'] = $detail['dialplan_detail_enabled'] ?? 'true';
						$database = new database;
						$database->execute($sql, $parameters);
						unset($sql, $parameters);
					}
				}
			}
		}
	}

	/**
	 * Clone IVR menus from source domain
	 * @param string $source_uuid  Source domain UUID
	 * @param string $target_uuid  Target domain UUID
	 * @param int    $count        Number of IVRs to clone
	 * @return int   Number of IVRs created
	 */
	public function clone_ivrs($source_uuid, $target_uuid, $count) {
		$sql = "select * from v_ivr_menus where domain_uuid = :domain_uuid order by ivr_menu_name asc";
		$parameters['domain_uuid'] = $source_uuid;
		$database = new database;
		$source_ivrs = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		$created = 0;
		if (is_array($source_ivrs)) {
			foreach ($source_ivrs as $ivr) {
				if ($created >= $count) break;
				$old_ivr_uuid = $ivr['ivr_menu_uuid'];
				$new_ivr_uuid = uuid();

				$sql = "insert into v_ivr_menus (ivr_menu_uuid, domain_uuid, ivr_menu_name, ivr_menu_extension, ";
				$sql .= "ivr_menu_greet_long, ivr_menu_greet_short, ivr_menu_timeout, ivr_menu_exit_app, ";
				$sql .= "ivr_menu_max_failures, ivr_menu_max_timeouts, ivr_menu_digit_len, ivr_menu_direct_dial, ";
				$sql .= "ivr_menu_context, ivr_menu_enabled, ivr_menu_description) ";
				$sql .= "values (:ivr_menu_uuid, :domain_uuid, :name, :extension, ";
				$sql .= ":greet_long, :greet_short, :timeout, :exit_app, ";
				$sql .= ":max_failures, :max_timeouts, :digit_len, :direct_dial, ";
				$sql .= ":context, :enabled, :description) ";

				$parameters = [];
				$parameters['ivr_menu_uuid'] = $new_ivr_uuid;
				$parameters['domain_uuid'] = $target_uuid;
				$parameters['name'] = $ivr['ivr_menu_name'];
				$parameters['extension'] = $ivr['ivr_menu_extension'] ?? '';
				$parameters['greet_long'] = $ivr['ivr_menu_greet_long'] ?? '';
				$parameters['greet_short'] = $ivr['ivr_menu_greet_short'] ?? '';
				$parameters['timeout'] = $ivr['ivr_menu_timeout'] ?? '3000';
				$parameters['exit_app'] = $ivr['ivr_menu_exit_app'] ?? '';
				$parameters['max_failures'] = $ivr['ivr_menu_max_failures'] ?? '3';
				$parameters['max_timeouts'] = $ivr['ivr_menu_max_timeouts'] ?? '3';
				$parameters['digit_len'] = $ivr['ivr_menu_digit_len'] ?? '5';
				$parameters['direct_dial'] = $ivr['ivr_menu_direct_dial'] ?? 'false';
				$parameters['context'] = $target_uuid;
				$parameters['enabled'] = $ivr['ivr_menu_enabled'] ?? 'true';
				$parameters['description'] = 'Cloned by Domain Wizard';
				$database = new database;
				$database->execute($sql, $parameters);
				unset($sql, $parameters);

				//clone options
				$sql2 = "select * from v_ivr_menu_options where ivr_menu_uuid = :ivr_menu_uuid";
				$parameters2['ivr_menu_uuid'] = $old_ivr_uuid;
				$database = new database;
				$options = $database->select($sql2, $parameters2, 'all');
				unset($sql2, $parameters2);

				if (is_array($options) && sizeof($options) > 0) {
					foreach ($options as $opt) {
						$sql = "insert into v_ivr_menu_options (ivr_menu_option_uuid, ivr_menu_uuid, domain_uuid, ";
						$sql .= "ivr_menu_option_digits, ivr_menu_option_action, ivr_menu_option_param, ";
						$sql .= "ivr_menu_option_order, ivr_menu_option_description, ivr_menu_option_enabled) ";
						$sql .= "values (:uuid, :ivr_uuid, :domain_uuid, :digits, :action, :param, :ord, :desc, :enabled) ";
						$parameters = [];
						$parameters['uuid'] = uuid();
						$parameters['ivr_uuid'] = $new_ivr_uuid;
						$parameters['domain_uuid'] = $target_uuid;
						$parameters['digits'] = $opt['ivr_menu_option_digits'];
						$parameters['action'] = $opt['ivr_menu_option_action'] ?? '';
						$parameters['param'] = $opt['ivr_menu_option_param'] ?? '';
						$parameters['ord'] = $opt['ivr_menu_option_order'] ?? '0';
						$parameters['desc'] = $opt['ivr_menu_option_description'] ?? '';
						$parameters['enabled'] = $opt['ivr_menu_option_enabled'] ?? 'true';
						$database = new database;
						$database->execute($sql, $parameters);
						unset($sql, $parameters);
					}
				}
				$created++;
			}
		}
		return $created;
	}

	/**
	 * Clone ring groups from source domain
	 * @param string $source_uuid  Source domain UUID
	 * @param string $target_uuid  Target domain UUID
	 * @param int    $count        Number of ring groups to clone
	 * @return int   Number of ring groups created
	 */
	public function clone_ring_groups($source_uuid, $target_uuid, $count) {
		$sql = "select * from v_ring_groups where domain_uuid = :domain_uuid order by ring_group_name asc";
		$parameters['domain_uuid'] = $source_uuid;
		$database = new database;
		$source_rgs = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		$created = 0;
		if (is_array($source_rgs)) {
			foreach ($source_rgs as $rg) {
				if ($created >= $count) break;
				$old_rg_uuid = $rg['ring_group_uuid'];
				$new_rg_uuid = uuid();

				$sql = "insert into v_ring_groups (ring_group_uuid, domain_uuid, ring_group_name, ring_group_extension, ";
				$sql .= "ring_group_context, ring_group_strategy, ring_group_call_timeout, ring_group_enabled, ring_group_description) ";
				$sql .= "values (:ring_group_uuid, :domain_uuid, :name, :extension, :context, :strategy, :call_timeout, :enabled, :description) ";
				$parameters = [];
				$parameters['ring_group_uuid'] = $new_rg_uuid;
				$parameters['domain_uuid'] = $target_uuid;
				$parameters['name'] = $rg['ring_group_name'];
				$parameters['extension'] = $rg['ring_group_extension'] ?? '';
				$parameters['context'] = $target_uuid;
				$parameters['strategy'] = $rg['ring_group_strategy'] ?? 'simultaneous';
				$parameters['call_timeout'] = $rg['ring_group_call_timeout'] ?? '30';
				$parameters['enabled'] = $rg['ring_group_enabled'] ?? 'true';
				$parameters['description'] = 'Cloned by Domain Wizard';
				$database = new database;
				$database->execute($sql, $parameters);
				unset($sql, $parameters);

				//clone destinations
				$sql2 = "select * from v_ring_group_destinations where ring_group_uuid = :ring_group_uuid";
				$parameters2['ring_group_uuid'] = $old_rg_uuid;
				$database = new database;
				$destinations = $database->select($sql2, $parameters2, 'all');
				unset($sql2, $parameters2);

				if (is_array($destinations) && sizeof($destinations) > 0) {
					foreach ($destinations as $dest) {
						$sql = "insert into v_ring_group_destinations (ring_group_destination_uuid, ring_group_uuid, domain_uuid, ";
						$sql .= "destination_number, destination_delay, destination_timeout, destination_enabled) ";
						$sql .= "values (:uuid, :rg_uuid, :domain_uuid, :number, :delay, :timeout, :enabled) ";
						$parameters = [];
						$parameters['uuid'] = uuid();
						$parameters['rg_uuid'] = $new_rg_uuid;
						$parameters['domain_uuid'] = $target_uuid;
						$parameters['number'] = $dest['destination_number'] ?? '';
						$parameters['delay'] = $dest['destination_delay'] ?? '0';
						$parameters['timeout'] = $dest['destination_timeout'] ?? '30';
						$parameters['enabled'] = $dest['destination_enabled'] ?? 'true';
						$database = new database;
						$database->execute($sql, $parameters);
						unset($sql, $parameters);
					}
				}
				$created++;
			}
		}

		return $created;
	}

	/**
	 * Upload recording files for a domain
	 * @param string $target_uuid  Target domain UUID
	 * @param array  $files        PHP $_FILES array for recordings
	 * @return int   Number of files uploaded
	 */
	public function upload_recordings($target_uuid, $files) {
		$uploaded = 0;

		if (!is_array($files['name'])) {
			return $uploaded;
		}

		//determine the recordings directory
			$recordings_dir = $_SESSION['switch']['recordings']['dir'] ?? '/var/lib/freeswitch/recordings';
			$domain_dir = $recordings_dir . '/' . $target_uuid;

			if (!is_dir($domain_dir)) {
				mkdir($domain_dir, 0770, true);
			}

		$allowed_types = ['audio/wav', 'audio/x-wav', 'audio/mpeg', 'audio/mp3', 'audio/ogg'];

		for ($i = 0; $i < count($files['name']); $i++) {
			if ($files['error'][$i] !== UPLOAD_ERR_OK) {
				continue;
			}

			$filename = basename($files['name'][$i]);
			$tmp_file = $files['tmp_name'][$i];
			$file_type = $files['type'][$i] ?? '';

			//validate file type
				if (!in_array($file_type, $allowed_types)) {
					$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
					if (!in_array($ext, ['wav', 'mp3', 'ogg'])) {
						continue;
					}
				}

			//sanitize filename
				$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

			$dest = $domain_dir . '/' . $filename;
			if (move_uploaded_file($tmp_file, $dest)) {
				//set permissions
					chmod($dest, 0664);

				//save recording to database
					$recording_uuid = uuid();
					$array['v_recordings'][0]['recording_uuid'] = $recording_uuid;
					$array['v_recordings'][0]['domain_uuid'] = $target_uuid;
					$array['v_recordings'][0]['recording_filename'] = $filename;
					$array['v_recordings'][0]['recording_name'] = pathinfo($filename, PATHINFO_FILENAME);
					$array['v_recordings'][0]['recording_description'] = 'Uploaded by Domain Wizard';

					$p = new permissions;
					$p->add('v_recordings_add', 'temp');

					$database = new database;
					$database->app_name = 'domain_wizard';
					$database->app_uuid = '6e1d4a7c-2b8f-4e3d-9c5a-1d7b0e6f3a2c';
					$database->save($array);
					unset($array);

					$p->delete('v_recordings_add', 'temp');

				$uploaded++;
			}
		}

		return $uploaded;
	}

	/**
	 * Upload a single recording file for a domain
	 * @param string $domain_uuid  Domain UUID
	 * @param array  $file         Single $_FILES entry
	 * @return string|false  Filename on success, false on failure
	 */
	public function upload_single_recording($domain_uuid, $file) {
		if ($file['error'] !== UPLOAD_ERR_OK) return false;

		$recordings_dir = $_SESSION['switch']['recordings']['dir'] ?? '/var/lib/freeswitch/recordings';
		$domain_dir = $recordings_dir . '/' . $domain_uuid;
		if (!is_dir($domain_dir)) mkdir($domain_dir, 0770, true);

		$filename = basename($file['name']);
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if (!in_array($ext, ['wav', 'mp3', 'ogg'])) return false;

		$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
		$dest = $domain_dir . '/' . $filename;

		if (move_uploaded_file($file['tmp_name'], $dest)) {
			chmod($dest, 0664);

			$sql = "insert into v_recordings (recording_uuid, domain_uuid, recording_filename, recording_name, recording_description) ";
			$sql .= "values (:recording_uuid, :domain_uuid, :recording_filename, :recording_name, :recording_description) ";
			$parameters['recording_uuid'] = uuid();
			$parameters['domain_uuid'] = $domain_uuid;
			$parameters['recording_filename'] = $filename;
			$parameters['recording_name'] = pathinfo($filename, PATHINFO_FILENAME);
			$parameters['recording_description'] = 'IVR recording uploaded by Domain Wizard';
			$database = new database;
			$database->execute($sql, $parameters);
			unset($sql, $parameters);

			return $filename;
		}

		return false;
	}

	/**
	 * Create a custom SIP gateway for a domain
	 * @param string $domain_uuid  Domain UUID
	 * @param array  $config       Gateway config [name, proxy, username, password, register, transport, caller_id]
	 */
	public function create_gateway($domain_uuid, $config) {
		$gateway_uuid = uuid();
		$gw_name = !empty($config['name']) ? $config['name'] : $config['proxy'];

		$sql = "insert into v_gateways (gateway_uuid, domain_uuid, gateway, username, password, proxy, register, ";
		$sql .= "caller_id_in_from, sip_cid_type, enabled, description) ";
		$sql .= "values (:gateway_uuid, :domain_uuid, :gateway, :username, :password, :proxy, :register, ";
		$sql .= ":caller_id_in_from, :sip_cid_type, :enabled, :description) ";

		$parameters['gateway_uuid'] = $gateway_uuid;
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['gateway'] = $gw_name;
		$parameters['username'] = $config['username'] ?? '';
		$parameters['password'] = $config['password'] ?? '';
		$parameters['proxy'] = $config['proxy'] ?? '';
		$parameters['register'] = ($config['register'] ?? 'true') === 'true' ? 'true' : 'false';
		$parameters['caller_id_in_from'] = 'false';
		$parameters['sip_cid_type'] = 'none';
		$parameters['enabled'] = 'true';
		$parameters['description'] = 'Created by Domain Wizard';

		$database = new database;
		$database->execute($sql, $parameters);
		unset($sql, $parameters);

		return $gateway_uuid;
	}

	/**
	 * Create an admin user for a domain
	 * @param string $domain_uuid  Domain UUID
	 * @param string $username     Admin username
	 * @param string $password     Admin password
	 * @return string  User UUID
	 */
	public function create_admin_user($domain_uuid, $username, $password) {
		$user_uuid = uuid();
		$salt = uuid();
		$hashed_password = md5($salt . $password);

		//create the user using direct SQL
			$sql = "insert into v_users (user_uuid, domain_uuid, username, password, salt, user_enabled, add_date) ";
			$sql .= "values (:user_uuid, :domain_uuid, :username, :password, :salt, :user_enabled, now()) ";
			$parameters['user_uuid'] = $user_uuid;
			$parameters['domain_uuid'] = $domain_uuid;
			$parameters['username'] = $username;
			$parameters['password'] = $hashed_password;
			$parameters['salt'] = $salt;
			$parameters['user_enabled'] = 'true';
			$database = new database;
			$database->execute($sql, $parameters);
			unset($sql, $parameters);

		//assign the admin group
			$sql = "select group_uuid from v_groups where group_name = 'admin' limit 1";
			$database = new database;
			$group = $database->select($sql, null, 'row');
			unset($sql);

			if (is_array($group) && is_uuid($group['group_uuid'])) {
				$sql = "insert into v_user_groups (user_group_uuid, domain_uuid, group_name, group_uuid, user_uuid) ";
				$sql .= "values (:user_group_uuid, :domain_uuid, :group_name, :group_uuid, :user_uuid) ";
				$parameters['user_group_uuid'] = uuid();
				$parameters['domain_uuid'] = $domain_uuid;
				$parameters['group_name'] = 'admin';
				$parameters['group_uuid'] = $group['group_uuid'];
				$parameters['user_uuid'] = $user_uuid;
				$database = new database;
				$database->execute($sql, $parameters);
				unset($sql, $parameters);
			}

		//assign the user group
			$sql = "select group_uuid from v_groups where group_name = 'user' limit 1";
			$database = new database;
			$group = $database->select($sql, null, 'row');
			unset($sql);

			if (is_array($group) && is_uuid($group['group_uuid'])) {
				$sql = "insert into v_user_groups (user_group_uuid, domain_uuid, group_name, group_uuid, user_uuid) ";
				$sql .= "values (:user_group_uuid, :domain_uuid, :group_name, :group_uuid, :user_uuid) ";
				$parameters['user_group_uuid'] = uuid();
				$parameters['domain_uuid'] = $domain_uuid;
				$parameters['group_name'] = 'user';
				$parameters['group_uuid'] = $group['group_uuid'];
				$parameters['user_uuid'] = $user_uuid;
				$database = new database;
				$database->execute($sql, $parameters);
				unset($sql, $parameters);
			}

		return $user_uuid;
	}

	/**
	 * Get all enabled templates
	 * @return array  Array of templates
	 */
	public function get_templates() {
		$sql = "select * from v_domain_wizard_templates ";
		$sql .= "where enabled = 'true' ";
		$sql .= "order by template_name asc ";
		$database = new database;
		$result = $database->select($sql, null, 'all');
		unset($sql);
		return is_array($result) ? $result : [];
	}

	/**
	 * Log an action to the wizard logs table
	 * @param array $data  Log data
	 */
	public function log_action($data) {
		$array['v_domain_wizard_logs'][0]['domain_wizard_log_uuid'] = uuid();
		$array['v_domain_wizard_logs'][0]['domain_uuid'] = $data['domain_uuid'] ?? null;
		$array['v_domain_wizard_logs'][0]['template_uuid'] = $data['template_uuid'] ?? null;
		$array['v_domain_wizard_logs'][0]['created_by'] = $data['created_by'] ?? $_SESSION['user_uuid'] ?? null;
		$array['v_domain_wizard_logs'][0]['extensions_count'] = (int)($data['extensions_count'] ?? 0);
		$array['v_domain_wizard_logs'][0]['gateways_count'] = (int)($data['gateways_count'] ?? 0);
		$array['v_domain_wizard_logs'][0]['ivrs_count'] = (int)($data['ivrs_count'] ?? 0);
		$array['v_domain_wizard_logs'][0]['recordings_uploaded'] = (int)($data['recordings_uploaded'] ?? 0);
		$array['v_domain_wizard_logs'][0]['status'] = $data['status'] ?? 'unknown';
		$array['v_domain_wizard_logs'][0]['log_detail'] = $data['log_detail'] ?? '';
		$array['v_domain_wizard_logs'][0]['add_date'] = 'now()';

		$p = new permissions;
		$p->add('v_domain_wizard_logs_add', 'temp');

		$database = new database;
		$database->app_name = 'domain_wizard';
		$database->app_uuid = '6e1d4a7c-2b8f-4e3d-9c5a-1d7b0e6f3a2c';
		$database->save($array);
		unset($array);

		$p->delete('v_domain_wizard_logs_add', 'temp');
	}

}

?>
