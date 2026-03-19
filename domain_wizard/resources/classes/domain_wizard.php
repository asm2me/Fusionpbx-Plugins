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
		//get source extensions as templates
			$sql = "select * from v_extensions ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$sql .= "order by extension asc ";
			$sql .= "limit 1 ";
			$parameters['domain_uuid'] = $source_uuid;
			$database = new database;
			$source_ext = $database->select($sql, $parameters, 'row');
			unset($sql, $parameters);

		$created = 0;
		if (is_array($source_ext)) {
			$p = new permissions;
			$p->add('v_extensions_add', 'temp');

			for ($i = 0; $i < $count; $i++) {
				$ext_number = $start_number + $i;
				$new_ext_uuid = uuid();

				$array['v_extensions'][0]['extension_uuid'] = $new_ext_uuid;
				$array['v_extensions'][0]['domain_uuid'] = $target_uuid;
				$array['v_extensions'][0]['extension'] = (string)$ext_number;
				$array['v_extensions'][0]['number_alias'] = '';
				$array['v_extensions'][0]['password'] = bin2hex(random_bytes(6));
				$array['v_extensions'][0]['accountcode'] = '';
				$array['v_extensions'][0]['effective_caller_id_name'] = 'Extension ' . $ext_number;
				$array['v_extensions'][0]['effective_caller_id_number'] = (string)$ext_number;
				$array['v_extensions'][0]['outbound_caller_id_name'] = $source_ext['outbound_caller_id_name'] ?? '';
				$array['v_extensions'][0]['outbound_caller_id_number'] = $source_ext['outbound_caller_id_number'] ?? '';
				$array['v_extensions'][0]['emergency_caller_id_name'] = $source_ext['emergency_caller_id_name'] ?? '';
				$array['v_extensions'][0]['emergency_caller_id_number'] = $source_ext['emergency_caller_id_number'] ?? '';
				$array['v_extensions'][0]['directory_first_name'] = 'Extension';
				$array['v_extensions'][0]['directory_last_name'] = (string)$ext_number;
				$array['v_extensions'][0]['directory_visible'] = 'true';
				$array['v_extensions'][0]['directory_exten_visible'] = 'true';
				$array['v_extensions'][0]['max_registrations'] = $source_ext['max_registrations'] ?? '1';
				$array['v_extensions'][0]['limit_max'] = $source_ext['limit_max'] ?? '5';
				$array['v_extensions'][0]['limit_destination'] = $source_ext['limit_destination'] ?? 'error/user_busy';
				$array['v_extensions'][0]['user_context'] = $target_uuid;
				$array['v_extensions'][0]['missed_call_app'] = $source_ext['missed_call_app'] ?? '';
				$array['v_extensions'][0]['missed_call_data'] = $source_ext['missed_call_data'] ?? '';
				$array['v_extensions'][0]['toll_allow'] = $source_ext['toll_allow'] ?? '';
				$array['v_extensions'][0]['call_timeout'] = $source_ext['call_timeout'] ?? '30';
				$array['v_extensions'][0]['call_group'] = $source_ext['call_group'] ?? '';
				$array['v_extensions'][0]['call_screen_enabled'] = $source_ext['call_screen_enabled'] ?? 'false';
				$array['v_extensions'][0]['user_record'] = $source_ext['user_record'] ?? '';
				$array['v_extensions'][0]['hold_music'] = $source_ext['hold_music'] ?? '';
				$array['v_extensions'][0]['auth_acl'] = $source_ext['auth_acl'] ?? '';
				$array['v_extensions'][0]['cidr'] = $source_ext['cidr'] ?? '';
				$array['v_extensions'][0]['sip_force_contact'] = $source_ext['sip_force_contact'] ?? '';
				$array['v_extensions'][0]['sip_force_expires'] = $source_ext['sip_force_expires'] ?? '';
				$array['v_extensions'][0]['nibble_account'] = $source_ext['nibble_account'] ?? '';
				$array['v_extensions'][0]['absolute_codec_string'] = $source_ext['absolute_codec_string'] ?? '';
				$array['v_extensions'][0]['force_ping'] = $source_ext['force_ping'] ?? '';
				$array['v_extensions'][0]['dial_string'] = $source_ext['dial_string'] ?? '';
				$array['v_extensions'][0]['enabled'] = 'true';
				$array['v_extensions'][0]['description'] = 'Created by Domain Wizard';

				$database = new database;
				$database->app_name = 'domain_wizard';
				$database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
				$database->save($array);
				unset($array);

				$created++;
			}

			$p->delete('v_extensions_add', 'temp');
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
		//get source gateways
			$sql = "select * from v_gateways ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$sql .= "order by gateway_uuid asc ";
			$parameters['domain_uuid'] = $source_uuid;
			$database = new database;
			$source_gateways = $database->select($sql, $parameters, 'all');
			unset($sql, $parameters);

		$created = 0;
		if (is_array($source_gateways)) {
			$p = new permissions;
			$p->add('v_gateways_add', 'temp');

			foreach ($source_gateways as $gw) {
				if ($created >= $count) break;

				$new_gw_uuid = uuid();

				$array['v_gateways'][0]['gateway_uuid'] = $new_gw_uuid;
				$array['v_gateways'][0]['domain_uuid'] = $target_uuid;
				$array['v_gateways'][0]['gateway'] = $gw['gateway'] . '_clone';
				$array['v_gateways'][0]['username'] = $gw['username'] ?? '';
				$array['v_gateways'][0]['password'] = $gw['password'] ?? '';
				$array['v_gateways'][0]['from_user'] = $gw['from_user'] ?? '';
				$array['v_gateways'][0]['from_domain'] = $gw['from_domain'] ?? '';
				$array['v_gateways'][0]['proxy'] = $gw['proxy'] ?? '';
				$array['v_gateways'][0]['realm'] = $gw['realm'] ?? '';
				$array['v_gateways'][0]['expire_seconds'] = $gw['expire_seconds'] ?? '800';
				$array['v_gateways'][0]['register'] = $gw['register'] ?? 'false';
				$array['v_gateways'][0]['register_transport'] = $gw['register_transport'] ?? '';
				$array['v_gateways'][0]['retry_seconds'] = $gw['retry_seconds'] ?? '30';
				$array['v_gateways'][0]['context'] = $target_uuid;
				$array['v_gateways'][0]['profile'] = $gw['profile'] ?? 'external';
				$array['v_gateways'][0]['caller_id_in_from'] = $gw['caller_id_in_from'] ?? 'false';
				$array['v_gateways'][0]['supress_cng'] = $gw['supress_cng'] ?? '';
				$array['v_gateways'][0]['sip_cid_type'] = $gw['sip_cid_type'] ?? '';
				$array['v_gateways'][0]['codec_prefs'] = $gw['codec_prefs'] ?? '';
				$array['v_gateways'][0]['extension'] = $gw['extension'] ?? '';
				$array['v_gateways'][0]['extension_in_contact'] = $gw['extension_in_contact'] ?? '';
				$array['v_gateways'][0]['ping'] = $gw['ping'] ?? '';
				$array['v_gateways'][0]['channels'] = $gw['channels'] ?? '';
				$array['v_gateways'][0]['hostname'] = $gw['hostname'] ?? '';
				$array['v_gateways'][0]['enabled'] = 'false'; //disabled by default for safety
				$array['v_gateways'][0]['description'] = 'Cloned by Domain Wizard from ' . ($gw['gateway'] ?? '');

				$database = new database;
				$database->app_name = 'domain_wizard';
				$database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
				$database->save($array);
				unset($array);

				$created++;
			}

			$p->delete('v_gateways_add', 'temp');
		}

		return $created;
	}

	/**
	 * Clone dialplan entries from source domain
	 * @param string $source_uuid  Source domain UUID
	 * @param string $target_uuid  Target domain UUID
	 */
	private function clone_dialplan($source_uuid, $target_uuid) {
		//get source dialplan entries
			$sql = "select * from v_dialplans ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$sql .= "order by dialplan_order asc ";
			$parameters['domain_uuid'] = $source_uuid;
			$database = new database;
			$source_dialplans = $database->select($sql, $parameters, 'all');
			unset($sql, $parameters);

		if (is_array($source_dialplans)) {
			$p = new permissions;
			$p->add('v_dialplans_add', 'temp');

			foreach ($source_dialplans as $dp) {
				$old_dp_uuid = $dp['dialplan_uuid'];
				$new_dp_uuid = uuid();

				//clone the dialplan
					$array['v_dialplans'][0]['dialplan_uuid'] = $new_dp_uuid;
					$array['v_dialplans'][0]['domain_uuid'] = $target_uuid;
					$array['v_dialplans'][0]['app_uuid'] = $dp['app_uuid'] ?? '';
					$array['v_dialplans'][0]['dialplan_name'] = $dp['dialplan_name'];
					$array['v_dialplans'][0]['dialplan_number'] = $dp['dialplan_number'] ?? '';
					$array['v_dialplans'][0]['dialplan_context'] = $target_uuid;
					$array['v_dialplans'][0]['dialplan_continue'] = $dp['dialplan_continue'] ?? '';
					$array['v_dialplans'][0]['dialplan_order'] = $dp['dialplan_order'];
					$array['v_dialplans'][0]['dialplan_enabled'] = $dp['dialplan_enabled'];
					$array['v_dialplans'][0]['dialplan_description'] = $dp['dialplan_description'] ?? '';

					$database = new database;
					$database->app_name = 'domain_wizard';
					$database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
					$database->save($array);
					unset($array);

				//clone the dialplan details
					$sql2 = "select * from v_dialplan_details ";
					$sql2 .= "where dialplan_uuid = :dialplan_uuid ";
					$parameters2['dialplan_uuid'] = $old_dp_uuid;
					$database = new database;
					$details = $database->select($sql2, $parameters2, 'all');
					unset($sql2, $parameters2);

					if (is_array($details) && sizeof($details) > 0) {
						$d = 0;
						foreach ($details as $detail) {
							$array['v_dialplan_details'][$d]['dialplan_detail_uuid'] = uuid();
							$array['v_dialplan_details'][$d]['dialplan_uuid'] = $new_dp_uuid;
							$array['v_dialplan_details'][$d]['domain_uuid'] = $target_uuid;
							$array['v_dialplan_details'][$d]['dialplan_detail_tag'] = $detail['dialplan_detail_tag'];
							$array['v_dialplan_details'][$d]['dialplan_detail_type'] = $detail['dialplan_detail_type'];
							$array['v_dialplan_details'][$d]['dialplan_detail_data'] = $detail['dialplan_detail_data'];
							$array['v_dialplan_details'][$d]['dialplan_detail_break'] = $detail['dialplan_detail_break'] ?? '';
							$array['v_dialplan_details'][$d]['dialplan_detail_inline'] = $detail['dialplan_detail_inline'] ?? '';
							$array['v_dialplan_details'][$d]['dialplan_detail_group'] = $detail['dialplan_detail_group'] ?? '0';
							$array['v_dialplan_details'][$d]['dialplan_detail_order'] = $detail['dialplan_detail_order'];
							$array['v_dialplan_details'][$d]['dialplan_detail_enabled'] = $detail['dialplan_detail_enabled'] ?? 'true';
							$d++;
						}

						$p2 = new permissions;
						$p2->add('v_dialplan_details_add', 'temp');
						$database = new database;
						$database->app_name = 'domain_wizard';
						$database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
						$database->save($array);
						unset($array);
						$p2->delete('v_dialplan_details_add', 'temp');
					}
			}

			$p->delete('v_dialplans_add', 'temp');
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
		//get source IVR menus
			$sql = "select * from v_ivr_menus ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$sql .= "order by ivr_menu_name asc ";
			$parameters['domain_uuid'] = $source_uuid;
			$database = new database;
			$source_ivrs = $database->select($sql, $parameters, 'all');
			unset($sql, $parameters);

		$created = 0;
		if (is_array($source_ivrs)) {
			$p = new permissions;
			$p->add('v_ivr_menus_add', 'temp');

			foreach ($source_ivrs as $ivr) {
				if ($created >= $count) break;

				$old_ivr_uuid = $ivr['ivr_menu_uuid'];
				$new_ivr_uuid = uuid();

				//clone the IVR menu
					$array['v_ivr_menus'][0]['ivr_menu_uuid'] = $new_ivr_uuid;
					$array['v_ivr_menus'][0]['domain_uuid'] = $target_uuid;
					$array['v_ivr_menus'][0]['ivr_menu_name'] = $ivr['ivr_menu_name'];
					$array['v_ivr_menus'][0]['ivr_menu_extension'] = $ivr['ivr_menu_extension'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_greet_long'] = $ivr['ivr_menu_greet_long'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_greet_short'] = $ivr['ivr_menu_greet_short'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_invalid_sound'] = $ivr['ivr_menu_invalid_sound'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_exit_sound'] = $ivr['ivr_menu_exit_sound'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_confirm_macro'] = $ivr['ivr_menu_confirm_macro'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_confirm_key'] = $ivr['ivr_menu_confirm_key'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_tts_engine'] = $ivr['ivr_menu_tts_engine'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_tts_voice'] = $ivr['ivr_menu_tts_voice'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_confirm_attempts'] = $ivr['ivr_menu_confirm_attempts'] ?? '3';
					$array['v_ivr_menus'][0]['ivr_menu_timeout'] = $ivr['ivr_menu_timeout'] ?? '3000';
					$array['v_ivr_menus'][0]['ivr_menu_exit_app'] = $ivr['ivr_menu_exit_app'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_exit_data'] = $ivr['ivr_menu_exit_data'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_inter_digit_timeout'] = $ivr['ivr_menu_inter_digit_timeout'] ?? '2000';
					$array['v_ivr_menus'][0]['ivr_menu_max_failures'] = $ivr['ivr_menu_max_failures'] ?? '3';
					$array['v_ivr_menus'][0]['ivr_menu_max_timeouts'] = $ivr['ivr_menu_max_timeouts'] ?? '3';
					$array['v_ivr_menus'][0]['ivr_menu_digit_len'] = $ivr['ivr_menu_digit_len'] ?? '5';
					$array['v_ivr_menus'][0]['ivr_menu_direct_dial'] = $ivr['ivr_menu_direct_dial'] ?? 'false';
					$array['v_ivr_menus'][0]['ivr_menu_ring_back'] = $ivr['ivr_menu_ring_back'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_cid_prefix'] = $ivr['ivr_menu_cid_prefix'] ?? '';
					$array['v_ivr_menus'][0]['ivr_menu_context'] = $target_uuid;
					$array['v_ivr_menus'][0]['ivr_menu_enabled'] = $ivr['ivr_menu_enabled'] ?? 'true';
					$array['v_ivr_menus'][0]['ivr_menu_description'] = 'Cloned by Domain Wizard';

					$database = new database;
					$database->app_name = 'domain_wizard';
					$database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
					$database->save($array);
					unset($array);

				//clone IVR menu options
					$sql2 = "select * from v_ivr_menu_options ";
					$sql2 .= "where ivr_menu_uuid = :ivr_menu_uuid ";
					$parameters2['ivr_menu_uuid'] = $old_ivr_uuid;
					$database = new database;
					$options = $database->select($sql2, $parameters2, 'all');
					unset($sql2, $parameters2);

					if (is_array($options) && sizeof($options) > 0) {
						$o = 0;
						foreach ($options as $opt) {
							$array['v_ivr_menu_options'][$o]['ivr_menu_option_uuid'] = uuid();
							$array['v_ivr_menu_options'][$o]['ivr_menu_uuid'] = $new_ivr_uuid;
							$array['v_ivr_menu_options'][$o]['domain_uuid'] = $target_uuid;
							$array['v_ivr_menu_options'][$o]['ivr_menu_option_digits'] = $opt['ivr_menu_option_digits'];
							$array['v_ivr_menu_options'][$o]['ivr_menu_option_action'] = $opt['ivr_menu_option_action'] ?? '';
							$array['v_ivr_menu_options'][$o]['ivr_menu_option_param'] = $opt['ivr_menu_option_param'] ?? '';
							$array['v_ivr_menu_options'][$o]['ivr_menu_option_order'] = $opt['ivr_menu_option_order'] ?? '0';
							$array['v_ivr_menu_options'][$o]['ivr_menu_option_description'] = $opt['ivr_menu_option_description'] ?? '';
							$array['v_ivr_menu_options'][$o]['ivr_menu_option_enabled'] = $opt['ivr_menu_option_enabled'] ?? 'true';
							$o++;
						}

						$p2 = new permissions;
						$p2->add('v_ivr_menu_options_add', 'temp');
						$database = new database;
						$database->app_name = 'domain_wizard';
						$database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
						$database->save($array);
						unset($array);
						$p2->delete('v_ivr_menu_options_add', 'temp');
					}

				$created++;
			}

			$p->delete('v_ivr_menus_add', 'temp');
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
		//get source ring groups
			$sql = "select * from v_ring_groups ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$sql .= "order by ring_group_name asc ";
			$parameters['domain_uuid'] = $source_uuid;
			$database = new database;
			$source_rgs = $database->select($sql, $parameters, 'all');
			unset($sql, $parameters);

		$created = 0;
		if (is_array($source_rgs)) {
			$p = new permissions;
			$p->add('v_ring_groups_add', 'temp');

			foreach ($source_rgs as $rg) {
				if ($created >= $count) break;

				$old_rg_uuid = $rg['ring_group_uuid'];
				$new_rg_uuid = uuid();

				//clone the ring group
					$array['v_ring_groups'][0]['ring_group_uuid'] = $new_rg_uuid;
					$array['v_ring_groups'][0]['domain_uuid'] = $target_uuid;
					$array['v_ring_groups'][0]['ring_group_name'] = $rg['ring_group_name'];
					$array['v_ring_groups'][0]['ring_group_extension'] = $rg['ring_group_extension'] ?? '';
					$array['v_ring_groups'][0]['ring_group_greeting'] = $rg['ring_group_greeting'] ?? '';
					$array['v_ring_groups'][0]['ring_group_context'] = $target_uuid;
					$array['v_ring_groups'][0]['ring_group_strategy'] = $rg['ring_group_strategy'] ?? 'simultaneous';
					$array['v_ring_groups'][0]['ring_group_timeout_app'] = $rg['ring_group_timeout_app'] ?? '';
					$array['v_ring_groups'][0]['ring_group_timeout_data'] = $rg['ring_group_timeout_data'] ?? '';
					$array['v_ring_groups'][0]['ring_group_call_timeout'] = $rg['ring_group_call_timeout'] ?? '30';
					$array['v_ring_groups'][0]['ring_group_caller_id_name'] = $rg['ring_group_caller_id_name'] ?? '';
					$array['v_ring_groups'][0]['ring_group_caller_id_number'] = $rg['ring_group_caller_id_number'] ?? '';
					$array['v_ring_groups'][0]['ring_group_cid_name_prefix'] = $rg['ring_group_cid_name_prefix'] ?? '';
					$array['v_ring_groups'][0]['ring_group_cid_number_prefix'] = $rg['ring_group_cid_number_prefix'] ?? '';
					$array['v_ring_groups'][0]['ring_group_distinctive_ring'] = $rg['ring_group_distinctive_ring'] ?? '';
					$array['v_ring_groups'][0]['ring_group_ring_back'] = $rg['ring_group_ring_back'] ?? '';
					$array['v_ring_groups'][0]['ring_group_follow_me_enabled'] = $rg['ring_group_follow_me_enabled'] ?? 'false';
					$array['v_ring_groups'][0]['ring_group_missed_call_app'] = $rg['ring_group_missed_call_app'] ?? '';
					$array['v_ring_groups'][0]['ring_group_missed_call_data'] = $rg['ring_group_missed_call_data'] ?? '';
					$array['v_ring_groups'][0]['ring_group_forward_enabled'] = $rg['ring_group_forward_enabled'] ?? 'false';
					$array['v_ring_groups'][0]['ring_group_forward_destination'] = $rg['ring_group_forward_destination'] ?? '';
					$array['v_ring_groups'][0]['ring_group_forward_toll_allow'] = $rg['ring_group_forward_toll_allow'] ?? '';
					$array['v_ring_groups'][0]['ring_group_enabled'] = $rg['ring_group_enabled'] ?? 'true';
					$array['v_ring_groups'][0]['ring_group_description'] = 'Cloned by Domain Wizard';

					$database = new database;
					$database->app_name = 'domain_wizard';
					$database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
					$database->save($array);
					unset($array);

				//clone ring group destinations
					$sql2 = "select * from v_ring_group_destinations ";
					$sql2 .= "where ring_group_uuid = :ring_group_uuid ";
					$parameters2['ring_group_uuid'] = $old_rg_uuid;
					$database = new database;
					$destinations = $database->select($sql2, $parameters2, 'all');
					unset($sql2, $parameters2);

					if (is_array($destinations) && sizeof($destinations) > 0) {
						$d = 0;
						foreach ($destinations as $dest) {
							$array['v_ring_group_destinations'][$d]['ring_group_destination_uuid'] = uuid();
							$array['v_ring_group_destinations'][$d]['ring_group_uuid'] = $new_rg_uuid;
							$array['v_ring_group_destinations'][$d]['domain_uuid'] = $target_uuid;
							$array['v_ring_group_destinations'][$d]['destination_number'] = $dest['destination_number'] ?? '';
							$array['v_ring_group_destinations'][$d]['destination_delay'] = $dest['destination_delay'] ?? '0';
							$array['v_ring_group_destinations'][$d]['destination_timeout'] = $dest['destination_timeout'] ?? '30';
							$array['v_ring_group_destinations'][$d]['destination_prompt'] = $dest['destination_prompt'] ?? '';
							$array['v_ring_group_destinations'][$d]['destination_enabled'] = $dest['destination_enabled'] ?? 'true';
							$d++;
						}

						$p2 = new permissions;
						$p2->add('v_ring_group_destinations_add', 'temp');
						$database = new database;
						$database->app_name = 'domain_wizard';
						$database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
						$database->save($array);
						unset($array);
						$p2->delete('v_ring_group_destinations_add', 'temp');
					}

				$created++;
			}

			$p->delete('v_ring_groups_add', 'temp');
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
					$database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
					$database->save($array);
					unset($array);

					$p->delete('v_recordings_add', 'temp');

				$uploaded++;
			}
		}

		return $uploaded;
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
		$database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
		$database->save($array);
		unset($array);

		$p->delete('v_domain_wizard_logs_add', 'temp');
	}

}

?>
