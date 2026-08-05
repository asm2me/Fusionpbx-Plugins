<?php

/*
	FusionPBX - Support Tickets SMS Log
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Shows every SMS attempt (ticket notifications and test sends) for
	this domain, with success/failure and the gateway's error message.
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

//filters
	$filter_result = $_GET['result'] ?? '';
	$limit = 100;

	$sql  = "SELECT l.*, t.ticket_number AS linked_ticket_number ";
	$sql .= "FROM v_ticket_sms_log l ";
	$sql .= "LEFT JOIN v_tickets t ON t.ticket_uuid = l.ticket_uuid ";
	$sql .= "WHERE l.domain_uuid = :domain_uuid ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];

	if ($filter_result === 'success') {
		$sql .= "AND l.success = true ";
	} elseif ($filter_result === 'failed') {
		$sql .= "AND l.success = false ";
	}

	$sql .= "ORDER BY l.insert_date DESC LIMIT :limit";
	$parameters['limit'] = $limit;

	$database = new database;
	$logs = $database->select($sql, $parameters, 'all') ?: [];
	unset($sql, $parameters);

//include header
	$document['title'] = "SMS Log";
	require_once "resources/header.php";

?>

<link rel="stylesheet" href="/app/tickets/resources/css/tickets.css">

<div class="action_bar" id="action_bar">
	<div class="heading"><b>SMS Log</b></div>
	<div class="actions">
		<a href="ticket_sms_settings.php" class="btn btn-default btn-sm"><i class="fa-solid fa-sms"></i> SMS Settings</a>
		<a href="tickets.php" class="btn btn-default btn-sm"><?php echo $text['button-back']; ?></a>
	</div>
</div>

<div class="card tickets-card">
	<div class="tickets-filters">
		<form method="get" class="tickets-filter-form">
			<div class="filter-group">
				<label>Filter</label>
				<select name="result" onchange="this.form.submit()">
					<option value="">All</option>
					<option value="success" <?php echo $filter_result === 'success' ? 'selected' : ''; ?>>Success only</option>
					<option value="failed" <?php echo $filter_result === 'failed' ? 'selected' : ''; ?>>Failed only</option>
				</select>
			</div>
		</form>
	</div>

	<?php if (count($logs) === 0) { ?>
		<div class="tickets-empty">
			<i class="fa-solid fa-comment-sms" style="font-size:48px;color:#ccc;"></i>
			<p>No SMS attempts logged yet.</p>
		</div>
	<?php } else { ?>
		<table class="table tickets-table">
			<thead>
				<tr>
					<th>Date</th>
					<th>Ticket</th>
					<th>Event</th>
					<th>Recipient</th>
					<th>To</th>
					<th>Gateway</th>
					<th>Result</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($logs as $log) { ?>
					<tr>
						<td><?php echo date('Y-m-d H:i:s', strtotime($log['insert_date'])); ?></td>
						<td>
							<?php if (!empty($log['linked_ticket_number']) && !empty($log['ticket_uuid'])) { ?>
								<a href="ticket_detail.php?id=<?php echo urlencode($log['ticket_uuid']); ?>"><?php echo htmlspecialchars($log['linked_ticket_number']); ?></a>
							<?php } else { ?>
								-
							<?php } ?>
						</td>
						<td><?php echo htmlspecialchars($log['event']); ?></td>
						<td><?php echo htmlspecialchars($log['recipient_type']); ?></td>
						<td><?php echo htmlspecialchars($log['to_number']); ?></td>
						<td><?php echo htmlspecialchars($log['gateway_type'] ?? '-'); ?></td>
						<td>
							<?php if ($log['success'] === 't' || $log['success'] === true) { ?>
								<span class="ticket-badge badge-open">Sent</span>
							<?php } else { ?>
								<span class="ticket-badge badge-closed" title="<?php echo htmlspecialchars($log['error_message'] ?? ''); ?>">Failed</span>
								<?php if (!empty($log['error_message'])) { ?>
									<br><small class="text-danger"><?php echo htmlspecialchars($log['error_message']); ?></small>
								<?php } ?>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
		<p><small>Showing the most recent <?php echo $limit; ?> attempts.</small></p>
	<?php } ?>
</div>

<?php

//include footer
	require_once "resources/footer.php";

?>
