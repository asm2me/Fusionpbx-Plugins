<?php

	//application details
		$apps[$x]['name'] = 'Recording Normalize';
		$apps[$x]['uuid'] = '56174335-29ed-4485-a6fa-1a79b0dbd5e8';
		$apps[$x]['category'] = 'Switch';
		$apps[$x]['subcategory'] = '';
		$apps[$x]['version'] = '1.0.0';
		$apps[$x]['license'] = 'Mozilla Public License 1.1';
		$apps[$x]['url'] = '';
		$apps[$x]['description']['en-us'] = 'Normalize the volume of stored recordings (IVR greetings and prompts) so every prompt plays at the same level. Originals are kept and can be restored.';

	//permission details
		$y = 0;
		$apps[$x]['permissions'][$y]['name'] = 'recording_normalize_view';
		$apps[$x]['permissions'][$y]['menu']['uuid'] = '01da39c0-7c6a-47a1-816c-cc75979127d1';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		$apps[$x]['permissions'][$y]['groups'][] = 'admin';
		$y++;
		$apps[$x]['permissions'][$y]['name'] = 'recording_normalize_edit';
		$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
		$apps[$x]['permissions'][$y]['groups'][] = 'admin';

	//default settings
		$recording_normalize_settings = [
			['44388944-0880-4a80-b0d2-41e2f17d183d', 'mode', 'text', 'peak', 'true', 'peak (loudest point to peak_percent of full scale) or loudness (EBU R128, needs ffmpeg).'],
			['38c6f238-b9a2-44e8-bb7b-0c02687e0525', 'target_lufs', 'numeric', '-18', 'true', 'Loudness target in LUFS for loudness mode. -16 is louder, -20 quieter.'],
			['3fa6614a-ba26-49cb-9a63-f8b369f3ad78', 'true_peak', 'numeric', '-1.5', 'true', 'Maximum true peak in dBTP for loudness mode.'],
			['35e6af7d-8aa5-4c44-b706-88e67fee3e1d', 'peak_percent', 'numeric', '90', 'true', 'Peak mode: loudest point is set to this percent of full scale (90 = -0.92 dBFS).'],
			['2ee469c1-5f4b-4e38-9bb9-b9f39c034dfd', 'auto_normalize', 'boolean', 'false', 'true', 'When true, recording_normalize_cron.php normalizes new and re-uploaded recordings.'],
			['7a9a049f-b090-49d7-9773-b63692eb4630', 'auto_ivr_only', 'boolean', 'true', 'true', 'When true, auto normalize only touches recordings used by an IVR menu.'],
		];
		$y = 0;
		foreach ($recording_normalize_settings as $recording_normalize_setting) {
			$apps[$x]['default_settings'][$y]['default_setting_uuid'] = $recording_normalize_setting[0];
			$apps[$x]['default_settings'][$y]['default_setting_category'] = 'recording_normalize';
			$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = $recording_normalize_setting[1];
			$apps[$x]['default_settings'][$y]['default_setting_name'] = $recording_normalize_setting[2];
			$apps[$x]['default_settings'][$y]['default_setting_value'] = $recording_normalize_setting[3];
			$apps[$x]['default_settings'][$y]['default_setting_enabled'] = $recording_normalize_setting[4];
			$apps[$x]['default_settings'][$y]['default_setting_description'] = $recording_normalize_setting[5];
			$y++;
		}
		unset($recording_normalize_settings, $recording_normalize_setting);

?>
