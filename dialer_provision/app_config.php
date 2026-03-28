<?php

	//application details
		$apps[$x]['name'] = 'Dialer Provision';
		$apps[$x]['uuid'] = '9f3a7c82-1b4e-4d6f-a8c5-2e1d0f5b9a3c';
		$apps[$x]['category'] = 'Admin';
		$apps[$x]['subcategory'] = '';
		$apps[$x]['version'] = '1.0.0';
		$apps[$x]['license'] = 'Mozilla Public License 1.1';
		$apps[$x]['url'] = '';
		$apps[$x]['description']['en-us'] = 'Generate one-click provisioning links for VOIP@ Dialer apps (Windows, Android, iOS, macOS, Linux). Send the link via WhatsApp or SMS — the app installs and configures automatically.';

	//permission details
		$y = 0;
		$apps[$x]['permissions'][$y]['name'] = 'dialer_provision_view';
		$apps[$x]['permissions'][$y]['menu']['uuid'] = '7b2e8f4a-3c9d-4e1b-a6f8-5d0c7a2e3b1f';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;
		$apps[$x]['permissions'][$y]['name'] = 'dialer_provision_generate';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		$apps[$x]['permissions'][$y]['groups'][] = 'admin';

	//default settings
		$y = 0;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = 'f1e2d3c4-b5a6-7890-1234-56789abcdef0';
		$apps[$x]['default_settings'][$y]['default_setting_category'] = 'dialer_provision';
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = 'provision_url';
		$apps[$x]['default_settings'][$y]['default_setting_name'] = 'text';
		$apps[$x]['default_settings'][$y]['default_setting_value'] = 'https://voipat.com/provision';
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = 'true';
		$apps[$x]['default_settings'][$y]['default_setting_description'] = 'Base URL for provisioning landing page.';
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = 'f1e2d3c4-b5a6-7890-1234-56789abcdef1';
		$apps[$x]['default_settings'][$y]['default_setting_category'] = 'dialer_provision';
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = 'default_stun';
		$apps[$x]['default_settings'][$y]['default_setting_name'] = 'text';
		$apps[$x]['default_settings'][$y]['default_setting_value'] = 'stun:stun.l.google.com:19302';
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = 'true';
		$apps[$x]['default_settings'][$y]['default_setting_description'] = 'Default STUN server for provisioning links.';
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = 'f1e2d3c4-b5a6-7890-1234-56789abcdef2';
		$apps[$x]['default_settings'][$y]['default_setting_category'] = 'dialer_provision';
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = 'api_base_url';
		$apps[$x]['default_settings'][$y]['default_setting_name'] = 'text';
		$apps[$x]['default_settings'][$y]['default_setting_value'] = 'http://127.0.0.1:3000';
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = 'true';
		$apps[$x]['default_settings'][$y]['default_setting_description'] = 'Base URL of the VOIP@ API bridge (local). Example: http://127.0.0.1:3000';

?>
