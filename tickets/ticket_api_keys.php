<?php

/*
	FusionPBX - Support Tickets API Key
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Lets a domain admin generate a domain-locked API key/secret pair for
	external integrations (create tickets, check ticket status) without
	being able to reach any other domain's tickets.
*/

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('ticket_api_manage')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//generate a new key/secret pair
	function ticket_api_generate_key() {
		return [
			'api_key' => 'tk_' . bin2hex(random_bytes(16)),
			'api_secret' => 'ts_' . bin2hex(random_bytes(32)),
		];
	}

//create token
	$token = new token;
	$token_hash = $token->create($_SERVER['PHP_SELF']);

//process form submission
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			$_SESSION['message'] = "Invalid token.";
			header("Location: ticket_api_keys.php");
			exit;
		}

		$post_action = $_POST['action'] ?? '';
		$database = new database;

		if ($post_action === 'generate') {
			//only one active key per domain; disable any existing ones
			$sql = "UPDATE v_ticket_api_keys SET enabled = false WHERE domain_uuid = :domain_uuid";
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$database->execute($sql, $parameters);
			unset($sql, $parameters);

			$keys = ticket_api_generate_key();
			$key_uuid = uuid();

			$sql  = "INSERT INTO v_ticket_api_keys ";
			$sql .= "(ticket_api_key_uuid, domain_uuid, user_uuid, api_key, api_secret, label, enabled, insert_date, insert_user) ";
			$sql .= "VALUES (:key_uuid, :domain_uuid, :user_uuid, :api_key, :api_secret, :label, true, now(), :insert_user)";
			$parameters['key_uuid'] = $key_uuid;
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$parameters['user_uuid'] = $_SESSION['user_uuid'];
			$parameters['api_key'] = $keys['api_key'];
			$parameters['api_secret'] = hash('sha256', $keys['api_secret']);
			$parameters['label'] = trim($_POST['label'] ?? '') ?: null;
			$parameters['insert_user'] = $_SESSION['user_uuid'];
			$database->execute($sql, $parameters);
			unset($sql, $parameters);

			//flash the secret once; it is never shown again (only the hash is stored)
			$_SESSION['ticket_api_new_key'] = $keys['api_key'];
			$_SESSION['ticket_api_new_secret'] = $keys['api_secret'];
			$_SESSION['message'] = "API key generated.";
		}
		elseif ($post_action === 'revoke') {
			$key_uuid = $_POST['ticket_api_key_uuid'] ?? '';
			if (is_uuid($key_uuid)) {
				$sql = "UPDATE v_ticket_api_keys SET enabled = false WHERE ticket_api_key_uuid = :key_uuid AND domain_uuid = :domain_uuid";
				$parameters['key_uuid'] = $key_uuid;
				$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
				$database->execute($sql, $parameters);
				unset($sql, $parameters);
				$_SESSION['message'] = "API key revoked.";
			}
		}

		header("Location: ticket_api_keys.php");
		exit;
	}

//show the newly generated secret once, then clear it
	$new_key = $_SESSION['ticket_api_new_key'] ?? null;
	$new_secret = $_SESSION['ticket_api_new_secret'] ?? null;
	unset($_SESSION['ticket_api_new_key'], $_SESSION['ticket_api_new_secret']);

//load existing keys for this domain
	$sql = "SELECT * FROM v_ticket_api_keys WHERE domain_uuid = :domain_uuid ORDER BY insert_date DESC";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$api_keys = $database->select($sql, $parameters, 'all') ?: [];
	unset($sql, $parameters);

//include header
	$document['title'] = $text['title-ticket_api_keys'];
	require_once "resources/header.php";

?>

<link rel="stylesheet" href="/app/tickets/resources/css/tickets.css">

<div class="action_bar" id="action_bar">
	<div class="heading"><b><?php echo $text['title-ticket_api_keys']; ?></b></div>
	<div class="actions">
		<a href="tickets.php" class="btn btn-default btn-sm"><?php echo $text['button-back']; ?></a>
	</div>
</div>

<div class="card tickets-card">
	<p><?php echo $text['label-ticket_api_keys_description']; ?></p>

	<?php if (!empty($new_key) && !empty($new_secret)) { ?>
		<div class="alert alert-warning">
			<b><?php echo $text['label-api_key']; ?>:</b> <?php echo htmlspecialchars($new_key); ?><br/>
			<b><?php echo $text['label-api_secret']; ?>:</b> <?php echo htmlspecialchars($new_secret); ?><br/>
			<small><?php echo $text['message-api_key_generated']; ?></small>
		</div>
	<?php } ?>

	<form method="post" style="margin-bottom: 20px;">
		<input type="hidden" name="<?php echo $token_hash['name']; ?>" value="<?php echo $token_hash['hash']; ?>">
		<input type="hidden" name="action" value="generate">
		<div class="form-group" style="display:flex; gap:10px; align-items:flex-end;">
			<div>
				<label for="label"><?php echo $text['label-key_label']; ?></label>
				<input type="text" id="label" name="label" class="form-control" maxlength="128" placeholder="e.g. CRM integration">
			</div>
			<button type="submit" class="btn btn-primary" onclick="return confirm('<?php echo $text['message-confirm_generate']; ?>');">
				<?php echo $text['button-generate_api_key']; ?>
			</button>
		</div>
	</form>

	<table class="table tickets-table">
		<thead>
			<tr>
				<th><?php echo $text['label-api_key']; ?></th>
				<th><?php echo $text['label-key_label']; ?></th>
				<th><?php echo $text['label-status']; ?></th>
				<th><?php echo $text['label-created']; ?></th>
				<th><?php echo $text['label-last_used']; ?></th>
				<th><?php echo $text['label-actions']; ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($api_keys as $key) { ?>
				<tr>
					<td><?php echo htmlspecialchars($key['api_key']); ?></td>
					<td><?php echo htmlspecialchars($key['label'] ?? '-'); ?></td>
					<td>
						<?php if ($key['enabled'] === 't' || $key['enabled'] === true) { ?>
							<span class="ticket-badge badge-open"><?php echo $text['label-enabled']; ?></span>
						<?php } else { ?>
							<span class="ticket-badge badge-closed"><?php echo $text['label-revoked']; ?></span>
						<?php } ?>
					</td>
					<td><?php echo date('Y-m-d H:i', strtotime($key['insert_date'])); ?></td>
					<td><?php echo !empty($key['last_used_date']) ? date('Y-m-d H:i', strtotime($key['last_used_date'])) : '-'; ?></td>
					<td>
						<?php if ($key['enabled'] === 't' || $key['enabled'] === true) { ?>
							<form method="post" style="display:inline;" onsubmit="return confirm('<?php echo $text['message-confirm_revoke']; ?>');">
								<input type="hidden" name="<?php echo $token_hash['name']; ?>" value="<?php echo $token_hash['hash']; ?>">
								<input type="hidden" name="action" value="revoke">
								<input type="hidden" name="ticket_api_key_uuid" value="<?php echo $key['ticket_api_key_uuid']; ?>">
								<button type="submit" class="btn btn-danger btn-xs"><?php echo $text['button-revoke']; ?></button>
							</form>
						<?php } ?>
					</td>
				</tr>
			<?php } ?>
			<?php if (count($api_keys) === 0) { ?>
				<tr><td colspan="6"><?php echo $text['label-no_api_keys']; ?></td></tr>
			<?php } ?>
		</tbody>
	</table>
</div>

<?php

//include footer
	require_once "resources/footer.php";

?>
