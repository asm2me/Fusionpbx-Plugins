<?php

/*
	FusionPBX - Support Tickets SMS Gateway Settings
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	A real form for configuring the Dinstar/GoIP SMS gateway per domain,
	instead of FusionPBX's generic "add a setting row" editor. Writes
	directly to v_domain_settings (category 'tickets_sms') - the same
	settings ticket_sms.php reads at send time.
*/

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('ticket_manage')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

	define('TICKET_SMS_APP_UUID', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890');

	$sms_fields = [
		'enabled' => 'boolean',
		'gateway_type' => 'text',
		'gateway_host' => 'text',
		'gateway_port' => 'text',
		'gateway_username' => 'text',
		'gateway_password' => 'text',
		'gateway_channel' => 'text',
		'verify_ssl' => 'boolean',
		'notify_support_enabled' => 'boolean',
		'notify_support_number' => 'text',
		'notify_support_events' => 'text',
		'notify_customer_enabled' => 'boolean',
		'notify_customer_events' => 'text',
	];

	function ticket_sms_save_setting($database, $domain_uuid, $subcategory, $type, $value, $user_uuid) {
		$sql = "SELECT domain_setting_uuid FROM v_domain_settings WHERE domain_uuid = :domain_uuid AND domain_setting_category = 'tickets_sms' AND domain_setting_subcategory = :subcategory";
		$existing_uuid = $database->select($sql, ['domain_uuid' => $domain_uuid, 'subcategory' => $subcategory], 'column');

		if ($existing_uuid) {
			$sql = "UPDATE v_domain_settings SET domain_setting_value = :value, domain_setting_enabled = true, update_date = now(), update_user = :user_uuid WHERE domain_setting_uuid = :uuid";
			$database->execute($sql, ['value' => $value, 'user_uuid' => $user_uuid, 'uuid' => $existing_uuid]);
		} else {
			$sql  = "INSERT INTO v_domain_settings ";
			$sql .= "(domain_setting_uuid, domain_uuid, app_uuid, domain_setting_category, domain_setting_subcategory, domain_setting_name, domain_setting_value, domain_setting_enabled, insert_date, insert_user) ";
			$sql .= "VALUES (:uuid, :domain_uuid, :app_uuid, 'tickets_sms', :subcategory, :type, :value, true, now(), :user_uuid)";
			$database->execute($sql, [
				'uuid' => uuid(),
				'domain_uuid' => $domain_uuid,
				'app_uuid' => TICKET_SMS_APP_UUID,
				'subcategory' => $subcategory,
				'type' => $type,
				'value' => $value,
				'user_uuid' => $user_uuid,
			]);
		}
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//process form submission
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$token_check = new token;
		if (!$token_check->validate($_SERVER['PHP_SELF'])) {
			$_SESSION['message'] = "Invalid token.";
			header("Location: ticket_sms_settings.php");
			exit;
		}

		$database = new database;
		$domain_uuid = $_SESSION['domain_uuid'];
		$user_uuid = $_SESSION['user_uuid'];

		$support_events = implode(',', $_POST['notify_support_events'] ?? []);
		$customer_events = implode(',', $_POST['notify_customer_events'] ?? []);

		$values = [
			'enabled' => isset($_POST['enabled']) ? 'true' : 'false',
			'gateway_type' => in_array($_POST['gateway_type'] ?? '', ['dinstar', 'goip']) ? $_POST['gateway_type'] : '',
			'gateway_host' => trim($_POST['gateway_host'] ?? ''),
			'gateway_port' => trim($_POST['gateway_port'] ?? ''),
			'gateway_username' => trim($_POST['gateway_username'] ?? ''),
			'gateway_password' => $_POST['gateway_password'] ?? '',
			'gateway_channel' => trim($_POST['gateway_channel'] ?? '0'),
			'verify_ssl' => isset($_POST['verify_ssl']) ? 'true' : 'false',
			'notify_support_enabled' => isset($_POST['notify_support_enabled']) ? 'true' : 'false',
			'notify_support_number' => trim($_POST['notify_support_number'] ?? ''),
			'notify_support_events' => $support_events,
			'notify_customer_enabled' => isset($_POST['notify_customer_enabled']) ? 'true' : 'false',
			'notify_customer_events' => $customer_events,
		];

		foreach ($values as $subcategory => $value) {
			ticket_sms_save_setting($database, $domain_uuid, $subcategory, $sms_fields[$subcategory], $value, $user_uuid);
		}

		$_SESSION['message'] = "SMS gateway settings saved.";
		header("Location: ticket_sms_settings.php");
		exit;
	}

//load current settings
	$sql = "SELECT domain_setting_subcategory, domain_setting_value FROM v_domain_settings WHERE domain_uuid = :domain_uuid AND domain_setting_category = 'tickets_sms'";
	$database = new database;
	$rows = $database->select($sql, ['domain_uuid' => $_SESSION['domain_uuid']], 'all') ?: [];
	$settings = [];
	foreach ($rows as $row) {
		$settings[$row['domain_setting_subcategory']] = $row['domain_setting_value'];
	}
	unset($sql, $rows, $row);

	function sms_val($settings, $name, $default = '') {
		return htmlspecialchars($settings[$name] ?? $default);
	}
	function sms_checked($settings, $name) {
		return (($settings[$name] ?? 'false') === 'true') ? 'checked' : '';
	}
	function sms_event_checked($settings, $name, $event) {
		$events = array_map('trim', explode(',', $settings[$name] ?? ''));
		return in_array($event, $events) ? 'checked' : '';
	}

//include header
	$document['title'] = "SMS Gateway Settings";
	require_once "resources/header.php";

?>

<link rel="stylesheet" href="/app/tickets/resources/css/tickets.css">

<div class="action_bar" id="action_bar">
	<div class="heading"><b>SMS Gateway Settings</b></div>
	<div class="actions">
		<a href="tickets.php" class="btn btn-default btn-sm"><?php echo $text['button-back']; ?></a>
	</div>
</div>

<div class="card tickets-card">
	<p>Send an SMS through a Dinstar or GoIP GSM gateway when a ticket is created, its status changes, or it gets an update. Configured per domain; this domain will not send anything until "Enable SMS notifications" is checked below.</p>

	<form method="post">
		<input type="hidden" name="<?php echo $token['name']; ?>" value="<?php echo $token['hash']; ?>">

		<div class="form-group">
			<label><input type="checkbox" name="enabled" <?php echo sms_checked($settings, 'enabled'); ?>> Enable SMS notifications</label>
		</div>

		<h4>Gateway Connection</h4>
		<div class="form-group">
			<label for="gateway_type">Gateway Type</label>
			<select id="gateway_type" name="gateway_type" class="form-control" style="width:200px;">
				<option value="">-- Select --</option>
				<option value="dinstar" <?php echo (($settings['gateway_type'] ?? '') === 'dinstar') ? 'selected' : ''; ?>>Dinstar</option>
				<option value="goip" <?php echo (($settings['gateway_type'] ?? '') === 'goip') ? 'selected' : ''; ?>>GoIP</option>
			</select>
		</div>
		<div class="form-group">
			<label for="gateway_host">Gateway Host</label>
			<input type="text" id="gateway_host" name="gateway_host" class="form-control" value="<?php echo sms_val($settings, 'gateway_host'); ?>" placeholder="e.g. 192.168.1.50">
		</div>
		<div class="form-group">
			<label for="gateway_port">Gateway Port</label>
			<input type="text" id="gateway_port" name="gateway_port" class="form-control" style="width:150px;" value="<?php echo sms_val($settings, 'gateway_port'); ?>" placeholder="443 (Dinstar) / 80 (GoIP)">
		</div>
		<div class="form-group">
			<label for="gateway_username">Gateway Username</label>
			<input type="text" id="gateway_username" name="gateway_username" class="form-control" value="<?php echo sms_val($settings, 'gateway_username'); ?>">
		</div>
		<div class="form-group">
			<label for="gateway_password">Gateway Password</label>
			<input type="password" id="gateway_password" name="gateway_password" class="form-control" value="<?php echo sms_val($settings, 'gateway_password'); ?>" autocomplete="new-password">
		</div>
		<div class="form-group">
			<label for="gateway_channel">GSM Channel/Port</label>
			<input type="text" id="gateway_channel" name="gateway_channel" class="form-control" style="width:150px;" value="<?php echo sms_val($settings, 'gateway_channel', '0'); ?>">
		</div>
		<div class="form-group">
			<label><input type="checkbox" name="verify_ssl" <?php echo sms_checked($settings, 'verify_ssl'); ?>> Verify gateway TLS certificate (Dinstar only; most units use a self-signed cert, leave unchecked unless you know otherwise)</label>
		</div>

		<h4>Notify Support</h4>
		<div class="form-group">
			<label><input type="checkbox" name="notify_support_enabled" <?php echo sms_checked($settings, 'notify_support_enabled'); ?>> Text a fixed support number</label>
		</div>
		<div class="form-group">
			<label for="notify_support_number">Support Number</label>
			<input type="text" id="notify_support_number" name="notify_support_number" class="form-control" value="<?php echo sms_val($settings, 'notify_support_number'); ?>" placeholder="+15551234567">
		</div>
		<div class="form-group">
			<label>On which events:</label><br>
			<label style="display:inline-block; margin-right:15px;"><input type="checkbox" name="notify_support_events[]" value="created" <?php echo sms_event_checked($settings, 'notify_support_events', 'created'); ?>> Ticket created</label>
			<label style="display:inline-block; margin-right:15px;"><input type="checkbox" name="notify_support_events[]" value="status_changed" <?php echo sms_event_checked($settings, 'notify_support_events', 'status_changed'); ?>> Status changed</label>
			<label style="display:inline-block;"><input type="checkbox" name="notify_support_events[]" value="updated" <?php echo sms_event_checked($settings, 'notify_support_events', 'updated'); ?>> Ticket updated</label>
		</div>

		<h4>Notify Customer</h4>
		<p><small>Uses the phone number linked to the ticket (call_number). Tickets without one won't text a customer regardless of this setting.</small></p>
		<div class="form-group">
			<label><input type="checkbox" name="notify_customer_enabled" <?php echo sms_checked($settings, 'notify_customer_enabled'); ?>> Text the customer</label>
		</div>
		<div class="form-group">
			<label>On which events:</label><br>
			<label style="display:inline-block; margin-right:15px;"><input type="checkbox" name="notify_customer_events[]" value="created" <?php echo sms_event_checked($settings, 'notify_customer_events', 'created'); ?>> Ticket created</label>
			<label style="display:inline-block; margin-right:15px;"><input type="checkbox" name="notify_customer_events[]" value="status_changed" <?php echo sms_event_checked($settings, 'notify_customer_events', 'status_changed'); ?>> Status changed</label>
			<label style="display:inline-block;"><input type="checkbox" name="notify_customer_events[]" value="updated" <?php echo sms_event_checked($settings, 'notify_customer_events', 'updated'); ?>> Ticket updated</label>
		</div>

		<div class="form-actions">
			<button type="submit" class="btn btn-primary">Save</button>
		</div>
	</form>
</div>

<?php

//include footer
	require_once "resources/footer.php";

?>
