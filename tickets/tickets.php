<?php

/*
	FusionPBX - Support Tickets List
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1
*/

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('ticket_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//handle delete (admin only)
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
		if (!permission_exists('ticket_delete')) {
			$_SESSION['message'] = $text['message-access_denied'];
			header("Location: tickets.php");
			exit;
		}
		//validate token
		if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
			$_SESSION['message'] = "Invalid token.";
			header("Location: tickets.php");
			exit;
		}
		$ticket_uuid = $_POST['ticket_uuid'] ?? '';
		if (is_uuid($ticket_uuid)) {
			//delete replies, attachments, status log, then ticket
			$sql = "DELETE FROM v_ticket_replies WHERE ticket_uuid = :ticket_uuid AND domain_uuid = :domain_uuid";
			$parameters['ticket_uuid'] = $ticket_uuid;
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$database = new database;
			$database->execute($sql, $parameters);
			unset($sql, $parameters);

			$sql = "DELETE FROM v_ticket_attachments WHERE ticket_uuid = :ticket_uuid AND domain_uuid = :domain_uuid";
			$parameters['ticket_uuid'] = $ticket_uuid;
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$database->execute($sql, $parameters);
			unset($sql, $parameters);

			$sql = "DELETE FROM v_ticket_status_log WHERE ticket_uuid = :ticket_uuid AND domain_uuid = :domain_uuid";
			$parameters['ticket_uuid'] = $ticket_uuid;
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$database->execute($sql, $parameters);
			unset($sql, $parameters);

			$sql = "DELETE FROM v_tickets WHERE ticket_uuid = :ticket_uuid AND domain_uuid = :domain_uuid";
			$parameters['ticket_uuid'] = $ticket_uuid;
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$database->execute($sql, $parameters);
			unset($sql, $parameters);

			$_SESSION['message'] = $text['message-ticket_deleted'];
		}
		header("Location: tickets.php");
		exit;
	}

//get filters
	$filter_status = $_GET['status'] ?? '';
	$search = $_GET['search'] ?? '';
	$order_by = $_GET['order_by'] ?? 'insert_date';
	$order_dir = (isset($_GET['order_dir']) && strtolower($_GET['order_dir']) === 'asc') ? 'asc' : 'desc';

//build query
	$sql = "SELECT t.*, u.username AS reporter_name, a.username AS assignee_name ";
	$sql .= "FROM v_tickets t ";
	$sql .= "LEFT JOIN v_users u ON u.user_uuid = t.user_uuid ";
	$sql .= "LEFT JOIN v_users a ON a.user_uuid = t.assigned_to ";
	$sql .= "WHERE t.domain_uuid = :domain_uuid ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];

	//non-admin users can only see their own tickets
	if (!permission_exists('ticket_manage')) {
		$sql .= "AND t.user_uuid = :user_uuid ";
		$parameters['user_uuid'] = $_SESSION['user_uuid'];
	}

	if (!empty($filter_status) && in_array($filter_status, ['open', 'in_progress', 'answered', 'resolved', 'closed'])) {
		$sql .= "AND t.status = :status ";
		$parameters['status'] = $filter_status;
	}

	if (!empty($search)) {
		$sql .= "AND (t.subject ILIKE :search OR t.ticket_number ILIKE :search OR t.call_number ILIKE :search) ";
		$parameters['search'] = '%' . $search . '%';
	}

	//sanitize order
	$allowed_order = ['insert_date', 'ticket_number', 'status', 'priority', 'subject'];
	if (!in_array($order_by, $allowed_order)) $order_by = 'insert_date';
	$sql .= "ORDER BY t." . $order_by . " " . $order_dir . " ";
	$sql .= "LIMIT 100 ";

	$database = new database;
	$tickets = $database->select($sql, $parameters, 'all') ?: [];
	unset($sql, $parameters);

//count open tickets
	$sql = "SELECT count(*) FROM v_tickets WHERE domain_uuid = :domain_uuid AND status IN ('open','in_progress')";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	if (!permission_exists('ticket_manage')) {
		$sql .= " AND user_uuid = :user_uuid";
		$parameters['user_uuid'] = $_SESSION['user_uuid'];
	}
	$open_count = $database->select($sql, $parameters, 'column') ?: 0;
	unset($sql, $parameters);

//status badge colors
	function ticket_status_class($status) {
		switch ($status) {
			case 'open': return 'badge-open';
			case 'in_progress': return 'badge-progress';
			case 'answered': return 'badge-answered';
			case 'resolved': return 'badge-resolved';
			case 'closed': return 'badge-closed';
			default: return '';
		}
	}

	function ticket_priority_class($priority) {
		switch ($priority) {
			case 'urgent': return 'priority-urgent';
			case 'high': return 'priority-high';
			case 'normal': return 'priority-normal';
			case 'low': return 'priority-low';
			default: return '';
		}
	}

	function ticket_source_icon($source) {
		switch ($source) {
			case 'webphone': return '<i class="fa-solid fa-headset"></i>';
			case 'dialer': return '<i class="fa-solid fa-mobile-screen"></i>';
			default: return '<i class="fa-solid fa-desktop"></i>';
		}
	}

//include header
	$document['title'] = $text['title-tickets'];
	require_once "resources/header.php";

?>

<link rel="stylesheet" href="/app/tickets/resources/css/tickets.css">

<div class="action_bar" id="action_bar">
	<div class="heading">
		<b><?php echo $text['title-tickets']; ?></b>
		<?php if ($open_count > 0) { ?>
			<span class="ticket-open-count"><?php echo $open_count; ?> open</span>
		<?php } ?>
	</div>
	<div class="actions">
		<?php if (permission_exists('ticket_add')) { ?>
			<a href="ticket_new.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> <?php echo $text['button-new_ticket']; ?></a>
		<?php } ?>
	</div>
</div>

<div class="card tickets-card">
	<!-- Filters -->
	<div class="tickets-filters">
		<form method="get" class="tickets-filter-form">
			<div class="filter-group">
				<label><?php echo $text['label-filter_status']; ?></label>
				<select name="status" onchange="this.form.submit()">
					<option value=""><?php echo $text['label-filter_all']; ?></option>
					<option value="open" <?php echo $filter_status === 'open' ? 'selected' : ''; ?>><?php echo $text['status-open']; ?></option>
					<option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>><?php echo $text['status-in_progress']; ?></option>
					<option value="answered" <?php echo $filter_status === 'answered' ? 'selected' : ''; ?>><?php echo $text['status-answered']; ?></option>
					<option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>><?php echo $text['status-resolved']; ?></option>
					<option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>><?php echo $text['status-closed']; ?></option>
				</select>
			</div>
			<div class="filter-group">
				<label><?php echo $text['label-search']; ?></label>
				<input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search tickets...">
				<button type="submit" class="btn btn-default btn-sm"><i class="fa-solid fa-search"></i></button>
			</div>
		</form>
	</div>

	<!-- Tickets Table -->
	<?php if (count($tickets) === 0) { ?>
		<div class="tickets-empty">
			<i class="fa-solid fa-ticket" style="font-size:48px;color:#ccc;"></i>
			<p><?php echo $text['label-no_tickets']; ?></p>
		</div>
	<?php } else { ?>
		<table class="table tickets-table">
			<thead>
				<tr>
					<th><?php echo $text['label-ticket_number']; ?></th>
					<th><?php echo $text['label-subject']; ?></th>
					<th><?php echo $text['label-status']; ?></th>
					<th><?php echo $text['label-priority']; ?></th>
					<th><?php echo $text['label-source']; ?></th>
					<?php if (permission_exists('ticket_manage')) { ?>
						<th><?php echo $text['label-assigned_to']; ?></th>
					<?php } ?>
					<th><?php echo $text['label-created']; ?></th>
					<th><?php echo $text['label-actions']; ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($tickets as $ticket) { ?>
					<tr class="ticket-row <?php echo $ticket['status'] === 'open' ? 'ticket-row-open' : ''; ?>">
						<td>
							<a href="ticket_detail.php?id=<?php echo urlencode($ticket['ticket_uuid']); ?>">
								<?php echo htmlspecialchars($ticket['ticket_number']); ?>
							</a>
						</td>
						<td>
							<a href="ticket_detail.php?id=<?php echo urlencode($ticket['ticket_uuid']); ?>">
								<?php echo htmlspecialchars($ticket['subject']); ?>
							</a>
							<?php if (!empty($ticket['call_number'])) { ?>
								<span class="ticket-call-badge" title="Linked to call: <?php echo htmlspecialchars($ticket['call_number']); ?>">
									<i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($ticket['call_number']); ?>
								</span>
							<?php } ?>
						</td>
						<td><span class="ticket-badge <?php echo ticket_status_class($ticket['status']); ?>"><?php echo $text['status-' . $ticket['status']] ?? $ticket['status']; ?></span></td>
						<td><span class="ticket-priority <?php echo ticket_priority_class($ticket['priority']); ?>"><?php echo $text['priority-' . $ticket['priority']] ?? $ticket['priority']; ?></span></td>
						<td><?php echo ticket_source_icon($ticket['source']); ?> <?php echo $text['source-' . $ticket['source']] ?? $ticket['source']; ?></td>
						<?php if (permission_exists('ticket_manage')) { ?>
							<td><?php echo htmlspecialchars($ticket['assignee_name'] ?? '-'); ?></td>
						<?php } ?>
						<td><?php echo date('Y-m-d H:i', strtotime($ticket['insert_date'])); ?></td>
						<td>
							<a href="ticket_detail.php?id=<?php echo urlencode($ticket['ticket_uuid']); ?>" class="btn btn-default btn-xs" title="View">
								<i class="fa-solid fa-eye"></i>
							</a>
							<?php if (permission_exists('ticket_delete')) { ?>
								<form method="post" style="display:inline;" onsubmit="return confirm('Delete this ticket?');">
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="ticket_uuid" value="<?php echo $ticket['ticket_uuid']; ?>">
									<input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
									<button type="submit" class="btn btn-danger btn-xs" title="Delete"><i class="fa-solid fa-trash"></i></button>
								</form>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	<?php } ?>
</div>

<?php

//include footer
	require_once "resources/footer.php";

?>
