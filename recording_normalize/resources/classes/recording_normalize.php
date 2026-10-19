<?php

/*
	FusionPBX - Recording Normalize
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Normalizes the level of stored recordings (IVR greetings, prompts) in place.
	The first time a file is normalized its original is copied to
	<recordings>/<domain>/.normalize/<file>, so normalizing again always starts
	from the original (levels never compound) and Restore puts it back.
	A sidecar <file>.json records the hash of the normalized file, which tells
	us whether the file was replaced (re-uploaded) after it was normalized.
*/

class recording_normalize {

	const BACKUP_DIR = '.normalize';

	public $settings = [];
	private $database;

	public function __construct() {
		$this->database = new database;
		$this->settings = $this->load_settings();
	}

	//read the recording_normalize default settings and the recordings dir
	private function load_settings() {
		$settings = [
			'mode' => 'peak',
			'target_lufs' => '-18',
			'true_peak' => '-1.5',
			'peak_percent' => '90',
			'auto_normalize' => 'false',
			'auto_ivr_only' => 'true',
			'recordings_dir' => '/var/lib/freeswitch/recordings',
		];
		$sql = "select default_setting_subcategory, default_setting_value
		        from v_default_settings
		        where default_setting_category = 'recording_normalize'
		          and default_setting_enabled = 'true'";
		$rows = $this->database->select($sql, null, 'all');
		if (is_array($rows)) {
			foreach ($rows as $row) {
				$settings[$row['default_setting_subcategory']] = $row['default_setting_value'];
			}
		}
		if (!empty($_SESSION['switch']['recordings']['dir'])) {
			$settings['recordings_dir'] = $_SESSION['switch']['recordings']['dir'];
		}
		else {
			$sql = "select default_setting_value from v_default_settings
			        where default_setting_category = 'switch'
			          and default_setting_subcategory = 'recordings'
			          and default_setting_name = 'dir'
			          and default_setting_enabled = 'true'";
			$dir = $this->database->select($sql, null, 'column');
			if (!empty($dir)) {
				$settings['recordings_dir'] = $dir;
			}
		}
		return $settings;
	}

	//locate a binary on the server
	public static function which($name) {
		foreach (['/usr/bin/', '/usr/local/bin/', '/bin/'] as $dir) {
			if (is_executable($dir.$name)) {
				return $dir.$name;
			}
		}
		return null;
	}

	public function tools() {
		return [
			'ffmpeg' => self::which('ffmpeg'),
			'ffprobe' => self::which('ffprobe'),
			'sox' => self::which('sox'),
		];
	}

	public function domain_dir($domain_name) {
		return rtrim($this->settings['recordings_dir'], '/').'/'.$domain_name;
	}

	//recordings of a domain, with the IVR menus that use each one
	public function get_recordings($domain_uuid, $domain_name) {
		$sql = "select recording_uuid, recording_name, recording_filename, recording_description,
		               case when recording_base64 is not null and recording_base64 <> '' then 'true' else 'false' end as has_base64
		        from v_recordings
		        where domain_uuid = :domain_uuid
		        order by recording_name asc";
		$recordings = $this->database->select($sql, ['domain_uuid' => $domain_uuid], 'all');
		if (!is_array($recordings)) {
			return [];
		}

		//map filenames to the IVR menus that play them
		$sql = "select ivr_menu_name, ivr_menu_greet_long, ivr_menu_greet_short,
		               ivr_menu_invalid_sound, ivr_menu_exit_sound
		        from v_ivr_menus
		        where domain_uuid = :domain_uuid";
		$menus = $this->database->select($sql, ['domain_uuid' => $domain_uuid], 'all');
		$used_by = [];
		if (is_array($menus)) {
			foreach ($menus as $menu) {
				foreach (['ivr_menu_greet_long', 'ivr_menu_greet_short', 'ivr_menu_invalid_sound', 'ivr_menu_exit_sound'] as $field) {
					if (!empty($menu[$field])) {
						$used_by[basename($menu[$field])][$menu['ivr_menu_name']] = true;
					}
				}
			}
		}

		$dir = $this->domain_dir($domain_name);
		foreach ($recordings as &$row) {
			$file = basename($row['recording_filename']);
			$row['ivr_menus'] = array_keys($used_by[$file] ?? []);
			$row['path'] = $dir.'/'.$file;
			$row['exists'] = is_file($row['path']);
			$row['state'] = $this->state($dir, $file);
		}
		return $recordings;
	}

	//original | normalized | changed | missing
	public function state($dir, $file) {
		$path = $dir.'/'.$file;
		if (!is_file($path)) {
			return ['status' => 'missing'];
		}
		$meta_file = $dir.'/'.self::BACKUP_DIR.'/'.$file.'.json';
		if (!is_file($meta_file)) {
			return ['status' => 'original'];
		}
		$meta = json_decode(file_get_contents($meta_file), true) ?: [];
		$meta['status'] = (($meta['hash'] ?? '') === sha1_file($path)) ? 'normalized' : 'changed';
		return $meta;
	}

	public function get_recording($domain_uuid, $recording_uuid) {
		$sql = "select recording_uuid, recording_filename,
		               case when recording_base64 is not null and recording_base64 <> '' then 'true' else 'false' end as has_base64
		        from v_recordings
		        where domain_uuid = :domain_uuid and recording_uuid = :recording_uuid";
		$row = $this->database->select($sql, ['domain_uuid' => $domain_uuid, 'recording_uuid' => $recording_uuid], 'row');
		return is_array($row) ? $row : null;
	}

	//normalize one recording; $options overrides mode/target_lufs/true_peak/peak_percent
	public function normalize($domain_uuid, $domain_name, $recording_uuid, $options = []) {
		$row = $this->get_recording($domain_uuid, $recording_uuid);
		if (!$row) {
			return ['ok' => false, 'message' => 'recording not found'];
		}
		$file = basename($row['recording_filename']);
		$dir = $this->domain_dir($domain_name);
		$path = $dir.'/'.$file;
		if (!is_file($path)) {
			return ['ok' => false, 'message' => $file.': file not found on disk'];
		}

		$backup_dir = $dir.'/'.self::BACKUP_DIR;
		if (!is_dir($backup_dir) && !@mkdir($backup_dir, 0770, true)) {
			return ['ok' => false, 'message' => $file.': cannot create '.$backup_dir];
		}
		$backup = $backup_dir.'/'.$file;
		$meta_file = $backup.'.json';

		//keep the original; a file replaced since its last normalize becomes the new original
		$state = $this->state($dir, $file);
		$new_backup = ($state['status'] !== 'normalized' || !is_file($backup));
		if ($new_backup && !@copy($path, $backup)) {
			return ['ok' => false, 'message' => $file.': cannot write backup'];
		}

		$opts = array_merge($this->settings, array_filter($options, function ($v) { return $v !== null && $v !== ''; }));
		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		$tmp = $backup_dir.'/tmp_'.bin2hex(random_bytes(6)).'.'.$ext;

		$result = $this->process($backup, $tmp, $opts);
		if (!$result['ok']) {
			@unlink($tmp);
			if ($new_backup) {
				@unlink($backup);
				@unlink($meta_file);
			}
			return ['ok' => false, 'message' => $file.': '.$result['message']];
		}

		//replace the live file, keeping its owner and mode
		$perms = fileperms($path) & 0777;
		if (!@rename($tmp, $path)) {
			@unlink($tmp);
			return ['ok' => false, 'message' => $file.': cannot replace file'];
		}
		@chmod($path, $perms);

		file_put_contents($meta_file, json_encode([
			'hash' => sha1_file($path),
			'mode' => $result['mode'],
			'target' => $result['target'],
			'before' => $result['before'],
			'date' => date('Y-m-d H:i:s'),
		]));

		if ($row['has_base64'] === 'true') {
			$this->update_base64($recording_uuid, $path);
		}

		return ['ok' => true, 'message' => $file.': '.$result['summary']];
	}

	//put the original back
	public function restore($domain_uuid, $domain_name, $recording_uuid) {
		$row = $this->get_recording($domain_uuid, $recording_uuid);
		if (!$row) {
			return ['ok' => false, 'message' => 'recording not found'];
		}
		$file = basename($row['recording_filename']);
		$dir = $this->domain_dir($domain_name);
		$path = $dir.'/'.$file;
		$backup = $dir.'/'.self::BACKUP_DIR.'/'.$file;
		$state = $this->state($dir, $file);
		if ($state['status'] === 'changed') {
			return ['ok' => false, 'message' => $file.': was replaced after normalizing, nothing to restore'];
		}
		if (!is_file($backup)) {
			return ['ok' => false, 'message' => $file.': no original saved'];
		}
		if (!@copy($backup, $path)) {
			return ['ok' => false, 'message' => $file.': cannot restore'];
		}
		@unlink($backup);
		@unlink($backup.'.json');
		if ($row['has_base64'] === 'true') {
			$this->update_base64($recording_uuid, $path);
		}
		return ['ok' => true, 'message' => $file.': original restored'];
	}

	private function update_base64($recording_uuid, $path) {
		$sql = "update v_recordings set recording_base64 = :base64 where recording_uuid = :recording_uuid";
		$this->database->execute($sql, ['base64' => base64_encode(file_get_contents($path)), 'recording_uuid' => $recording_uuid]);
	}

	private static function run($cmd, &$output = null) {
		$output = [];
		exec($cmd.' 2>&1', $output, $code);
		$output = implode("\n", $output);
		return $code;
	}

	//write a normalized copy of $in to $out in the same sample rate, channels and encoding
	private function process($in, $out, $opts) {
		$tools = $this->tools();
		$mode = ($opts['mode'] === 'loudness') ? 'loudness' : 'peak';
		$percent = min(100, max(1, (float)$opts['peak_percent']));
		$peak = round(20 * log10($percent / 100), 2);

		if (!$tools['ffmpeg']) {
			if (!$tools['sox']) {
				return ['ok' => false, 'message' => 'neither ffmpeg nor sox is installed'];
			}
			//sox only does peak normalization
			$cmd = escapeshellarg($tools['sox']).' '.escapeshellarg($in).' '.escapeshellarg($out).' norm '.escapeshellarg((string)$peak);
			if (self::run($cmd, $output) !== 0) {
				return ['ok' => false, 'message' => 'sox failed: '.substr($output, -300)];
			}
			return ['ok' => true, 'mode' => 'peak', 'target' => $percent.'%', 'before' => '', 'summary' => 'peak normalized to '.$percent.'% (sox)'];
		}

		$ffmpeg = escapeshellarg($tools['ffmpeg']);
		$format = $this->probe($in);
		$out_args = '';
		if (!empty($format['sample_rate'])) $out_args .= ' -ar '.(int)$format['sample_rate'];
		if (!empty($format['channels'])) $out_args .= ' -ac '.(int)$format['channels'];
		if (!empty($format['codec_name'])) {
			$codec = ($format['codec_name'] === 'mp3') ? 'libmp3lame' : $format['codec_name'];
			$out_args .= ' -c:a '.escapeshellarg($codec);
		}
		if (!empty($format['bit_rate']) && ($format['codec_name'] ?? '') === 'mp3') {
			$out_args .= ' -b:a '.(int)$format['bit_rate'];
		}

		if ($mode === 'peak') {
			self::run($ffmpeg.' -hide_banner -nostats -i '.escapeshellarg($in).' -af volumedetect -f null -', $output);
			if (!preg_match('/max_volume:\s*(-?[\d.]+|-inf) dB/', $output, $m) || $m[1] === '-inf') {
				return ['ok' => false, 'message' => 'audio is silent or unreadable'];
			}
			$gain = round($peak - (float)$m[1], 2);
			$cmd = $ffmpeg.' -y -hide_banner -loglevel error -i '.escapeshellarg($in).' -af '.escapeshellarg('volume='.$gain.'dB').$out_args.' '.escapeshellarg($out);
			if (self::run($cmd, $output) !== 0) {
				return ['ok' => false, 'message' => 'ffmpeg failed: '.substr($output, -300)];
			}
			$before = 100 * pow(10, (float)$m[1] / 20);
			$before = round($before, $before < 10 ? 1 : 0).'%';
			return ['ok' => true, 'mode' => 'peak', 'target' => $percent.'%', 'before' => $before,
				'summary' => 'peak '.$before.' → '.$percent.'% ('.sprintf('%+.1f', $gain).' dB)'];
		}

		//loudness: two-pass EBU R128 loudnorm, linear so the prompt is not compressed
		$i = (float)$opts['target_lufs'];
		$tp = (float)$opts['true_peak'];
		$lra = 11;
		self::run($ffmpeg.' -hide_banner -nostats -i '.escapeshellarg($in).' -af '.escapeshellarg("loudnorm=I=$i:TP=$tp:LRA=$lra:print_format=json").' -f null -', $output);
		if (!preg_match('/\{[^{}]*"input_i"[^{}]*\}/s', $output, $m) || !($measured = json_decode($m[0], true))) {
			return ['ok' => false, 'message' => 'could not measure loudness: '.substr($output, -300)];
		}
		if (!is_numeric($measured['input_i']) || (float)$measured['input_i'] < -70) {
			return ['ok' => false, 'message' => 'audio is silent or too short to measure'];
		}
		$filter = sprintf('loudnorm=I=%s:TP=%s:LRA=%s:measured_I=%s:measured_TP=%s:measured_LRA=%s:measured_thresh=%s:offset=%s:linear=true',
			$i, $tp, $lra, $measured['input_i'], $measured['input_tp'], $measured['input_lra'], $measured['input_thresh'], $measured['target_offset']);
		$cmd = $ffmpeg.' -y -hide_banner -loglevel error -i '.escapeshellarg($in).' -af '.escapeshellarg($filter).$out_args.' '.escapeshellarg($out);
		if (self::run($cmd, $output) !== 0) {
			return ['ok' => false, 'message' => 'ffmpeg failed: '.substr($output, -300)];
		}
		return ['ok' => true, 'mode' => 'loudness', 'target' => $i.' LUFS', 'before' => $measured['input_i'].' LUFS',
			'summary' => 'loudness '.$measured['input_i'].' → '.$i.' LUFS'];
	}

	//waveform: peak absolute amplitude (0..1 of full scale) for each of $buckets slices
	public function peaks($path, $buckets = 200) {
		$tools = $this->tools();
		$rate = 4000;
		if ($tools['ffmpeg']) {
			$cmd = escapeshellarg($tools['ffmpeg']).' -v error -i '.escapeshellarg($path).' -ac 1 -ar '.$rate.' -f s16le -';
		}
		elseif ($tools['sox']) {
			$cmd = escapeshellarg($tools['sox']).' '.escapeshellarg($path).' -t raw -e signed -b 16 -L -c 1 -r '.$rate.' -';
		}
		else {
			return null;
		}
		$pcm = shell_exec($cmd.' 2>/dev/null');
		$count = intdiv(strlen((string)$pcm), 2);
		if ($count === 0) {
			return null;
		}
		$samples = unpack('v*', $pcm);
		$size = max(1, $count / $buckets);
		$peaks = [];
		for ($b = 0; $b < $buckets; $b++) {
			$start = (int)floor($b * $size) + 1;
			$end = min($count, (int)floor(($b + 1) * $size));
			$max = 0;
			for ($i = $start; $i <= $end; $i++) {
				$v = $samples[$i];
				$v = ($v >= 32768) ? 65536 - $v : $v;
				if ($v > $max) $max = $v;
			}
			$peaks[] = round(min(1, $max / 32768), 3);
		}
		return ['peaks' => $peaks, 'duration' => round($count / $rate, 2)];
	}

	private function probe($in) {
		$ffprobe = $this->tools()['ffprobe'];
		if (!$ffprobe) {
			return [];
		}
		self::run(escapeshellarg($ffprobe).' -v error -select_streams a:0 -show_entries stream=sample_rate,channels,codec_name,bit_rate -of json '.escapeshellarg($in), $output);
		$json = json_decode($output, true);
		return $json['streams'][0] ?? [];
	}

}

?>
