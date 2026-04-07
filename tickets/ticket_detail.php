<?php

/*
	FusionPBX - Ticket Detail
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

//get ticket uuid
	$ticket_uuid = $_GET['id'] ?? '';
	if (!is_uuid($ticket_uuid)) {
		header("Location: tickets.php");
		exit;
	}

//load ticket
	$sql  = "SELECT t.*, u.username AS reporter_name, a.username AS assignee_name ";
	$sql .= "FROM v_tickets t ";
	$sql .= "LEFT JOIN v_users u ON u.user_uuid = t.user_uuid ";
	$sql .= "LEFT JOIN v_users a ON a.user_uuid = t.assigned_to ";
	$sql .= "WHERE t.ticket_uuid = :ticket_uuid AND t.domain_uuid = :domain_uuid ";
	$parameters['ticket_uuid'] = $ticket_uuid;
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];

	//non-admin users can only see their own tickets
	if (!permission_exists('ticket_manage')) {
		$sql .= "AND t.user_uuid = :user_uuid ";
		$parameters['user_uuid'] = $_SESSION['user_uuid'];
	}

	$database = new database;
	$ticket = $database->select($sql, $parameters, 'row');
	unset($sql, $parameters);

	if (empty($ticket)) {
		$_SESSION['message'] = "Ticket not found.";
		header("Location: tickets.php");
		exit;
	}

//handle reply
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
		//validate token
		if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
			$_SESSION['message'] = "Invalid token.";
			header("Location: ticket_detail.php?id=" . urlencode($ticket_uuid));
			exit;
		}

		$action = $_POST['action'];

		if ($action === 'reply' && permission_exists('ticket_reply')) {
			$reply_text = trim($_POST['reply_text'] ?? '');
			if (!empty($reply_text)) {
				$reply_uuid = uuid();
				$is_admin = permission_exists('ticket_manage') ? 'true' : 'false';

				$sql  = "INSERT INTO v_ticket_replies ";
				$sql .= "(ticket_reply_uuid, ticket_uuid, domain_uuid, user_uuid, reply_text, is_admin, insert_date, insert_user) ";
				$sql .= "VALUES (:reply_uuid, :ticket_uuid, :domain_uuid, :user_uuid, :reply_text, :is_admin, now(), :insert_user)";
				$parameters['reply_uuid'] = $reply_uuid;
				$parameters['ticket_uuid'] = $ticket_uuid;
				$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
				$parameters['user_uuid'] = $_SESSION['user_uuid'];
				$parameters['reply_text'] = $reply_text;
				$parameters['is_admin'] = $is_admin;
				$parameters['insert_user'] = $_SESSION['user_uuid'];
				$database = new database;
				$database->execute($sql, $parameters);
				unset($sql, $parameters);

				//if admin replies, set status to 'answered' (if currently open/in_progress)
				if (permission_exists('ticket_manage') && in_array($ticket['status'], ['open', 'in_progress'])) {
					$old_status = $ticket['status'];
					$sql = "UPDATE v_tickets SET status = 'answered', update_date = now(), update_user = :user_uuid WHERE ticket_uuid = :ticket_uuid";
					$parameters['user_uuid'] = $_SESSION['user_uuid'];
					$parameters['ticket_uuid'] = $ticket_uuid;
					$database->execute($sql, $parameters);
					unset($sql, $parameters);

					//log status change
					$log_uuid = uuid();
					$sql  = "INSERT INTO v_ticket_status_log ";
					$sql .= "(ticket_status_log_uuid, ticket_uuid, domain_uuid, old_status, new_status, changed_by, note, insert_date) ";
					$sql .= "VALUES (:log_uuid, :ticket_uuid, :domain_uuid, :old, :new, :user_uuid, 'Admin replied', now())";
					$parameters['log_uuid'] = $log_uuid;
					$parameters['ticket_uuid'] = $ticket_uuid;
					$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
					$parameters['old'] = $old_status;
					$parameters['new'] = 'answered';
					$parameters['user_uuid'] = $_SESSION['user_uuid'];
					$database->execute($sql, $parameters);
					unset($sql, $parameters);
				}

				//if user replies to answered/resolved ticket, reopen it
				if (!permission_exists('ticket_manage') && in_array($ticket['status'], ['answered', 'resolved'])) {
					$old_status = $ticket['status'];
					$sql = "UPDATE v_tickets SET status = 'open', update_date = now(), update_user = :user_uuid WHERE ticket_uuid = :ticket_uuid";
					$parameters['user_uuid'] = $_SESSION['user_uuid'];
					$parameters['ticket_uuid'] = $ticket_uuid;
					$database->execute($sql, $parameters);
					unset($sql, $parameters);

					$log_uuid = uuid();
					$sql  = "INSERT INTO v_ticket_status_log ";
					$sql .= "(ticket_status_log_uuid, ticket_uuid, domain_uuid, old_status, new_status, changed_by, note, insert_date) ";
					$sql .= "VALUES (:log_uuid, :ticket_uuid, :domain_uuid, :old, 'open', :user_uuid, 'User replied - reopened', now())";
					$parameters['log_uuid'] = $log_uuid;
					$parameters['ticket_uuid'] = $ticket_uuid;
					$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
					$parameters['old'] = $old_status;
					$parameters['user_uuid'] = $_SESSION['user_uuid'];
					$database->execute($sql, $parameters);
					unset($sql, $parameters);
				}

				$_SESSION['message'] = $text['message-reply_added'];
			}
		}

		if ($action === 'update_status' && permission_exists('ticket_manage')) {
			$new_status = $_POST['new_status'] ?? '';
			$resolved_note = trim($_POST['resolved_note'] ?? '');
			$assigned_to = $_POST['assigned_to'] ?? '';

			if (!empty($new_status) && in_array($new_status, ['open', 'in_progress', 'answered', 'resolved', 'closed'])) {
				$old_status = $ticket['status'];
				$sql = "UPDATE v_tickets SET status = :status, update_date = now(), update_user = :user_uuid";
				$parameters['status'] = $new_status;
				$parameters['user_uuid'] = $_SESSION['user_uuid'];

				if ($new_status === 'resolved' && !empty($resolved_note)) {
					$sql .= ", resolved_note = :note";
					$parameters['note'] = $resolved_note;
				}

				if (!empty($assigned_to) && is_uuid($assigned_to)) {
					$sql .= ", assigned_to = :assigned";
					$parameters['assigned'] = $assigned_to;
				}

				$sql .= " WHERE ticket_uuid = :ticket_uuid AND domain_uuid = :domain_uuid";
				$parameters['ticket_uuid'] = $ticket_uuid;
				$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
				$database = new database;
				$database->execute($sql, $parameters);
				unset($sql, $parameters);

				//log status change
				$log_uuid = uuid();
				$sql  = "INSERT INTO v_ticket_status_log ";
				$sql .= "(ticket_status_log_uuid, ticket_uuid, domain_uuid, old_status, new_status, changed_by, note, insert_date) ";
				$sql .= "VALUES (:log_uuid, :ticket_uuid, :domain_uuid, :old, :new, :user_uuid, :note, now())";
				$parameters['log_uuid'] = $log_uuid;
				$parameters['ticket_uuid'] = $ticket_uuid;
				$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
				$parameters['old'] = $old_status;
				$parameters['new'] = $new_status;
				$parameters['user_uuid'] = $_SESSION['user_uuid'];
				$parameters['note'] = $resolved_note;
				$database->execute($sql, $parameters);
				unset($sql, $parameters);

				$_SESSION['message'] = $text['message-ticket_updated'];
			}
		}

		header("Location: ticket_detail.php?id=" . urlencode($ticket_uuid));
		exit;
	}

//reload ticket after any action
	$sql  = "SELECT t.*, u.username AS reporter_name, a.username AS assignee_name ";
	$sql .= "FROM v_tickets t ";
	$sql .= "LEFT JOIN v_users u ON u.user_uuid = t.user_uuid ";
	$sql .= "LEFT JOIN v_users a ON a.user_uuid = t.assigned_to ";
	$sql .= "WHERE t.ticket_uuid = :ticket_uuid AND t.domain_uuid = :domain_uuid";
	$parameters['ticket_uuid'] = $ticket_uuid;
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$ticket = $database->select($sql, $parameters, 'row');
	unset($sql, $parameters);

//load replies
	$sql = "SELECT r.*, u.username FROM v_ticket_replies r LEFT JOIN v_users u ON u.user_uuid = r.user_uuid WHERE r.ticket_uuid = :ticket_uuid AND r.domain_uuid = :domain_uuid ORDER BY r.insert_date ASC";
	$parameters['ticket_uuid'] = $ticket_uuid;
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$replies = $database->select($sql, $parameters, 'all') ?: [];
	unset($sql, $parameters);

//load attachments
	$sql = "SELECT * FROM v_ticket_attachments WHERE ticket_uuid = :ticket_uuid AND domain_uuid = :domain_uuid ORDER BY insert_date ASC";
	$parameters['ticket_uuid'] = $ticket_uuid;
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$attachments = $database->select($sql, $parameters, 'all') ?: [];
	unset($sql, $parameters);

//load status history
	$sql = "SELECT l.*, u.username FROM v_ticket_status_log l LEFT JOIN v_users u ON u.user_uuid = l.changed_by WHERE l.ticket_uuid = :ticket_uuid AND l.domain_uuid = :domain_uuid ORDER BY l.insert_date ASC";
	$parameters['ticket_uuid'] = $ticket_uuid;
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$status_log = $database->select($sql, $parameters, 'all') ?: [];
	unset($sql, $parameters);

//load domain admins for assignment dropdown
	$admins = [];
	if (permission_exists('ticket_manage')) {
		$sql  = "SELECT u.user_uuid, u.username FROM v_users u ";
		$sql .= "JOIN v_user_groups g ON g.user_uuid = u.user_uuid ";
		$sql .= "WHERE u.domain_uuid = :domain_uuid ";
		$sql .= "AND g.group_name IN ('admin', 'superadmin') ";
		$sql .= "GROUP BY u.user_uuid, u.username ORDER BY u.username";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$admins = $database->select($sql, $parameters, 'all') ?: [];
		unset($sql, $parameters);
	}

//status badge helper
	function detail_status_class($status) {
		switch ($status) {
			case 'open': return 'badge-open';
			case 'in_progress': return 'badge-progress';
			case 'answered': return 'badge-answered';
			case 'resolved': return 'badge-resolved';
			case 'closed': return 'badge-closed';
			default: return '';
		}
	}

//include header
	$document['title'] = $text['title-ticket_detail'] . ' - ' . $ticket['ticket_number'];
	require_once "resources/header.php";

?>

<link rel="stylesheet" href="/app/tickets/resources/css/tickets.css">

<div class="action_bar" id="action_bar">
	<div class="heading">
		<b><?php echo $text['title-ticket_detail']; ?>: <?php echo htmlspecialchars($ticket['ticket_number']); ?></b>
		<span class="ticket-badge <?php echo detail_status_class($ticket['status']); ?>"><?php echo $text['status-' . $ticket['status']] ?? $ticket['status']; ?></span>
	</div>
	<div class="actions">
		<a href="tickets.php" class="btn btn-default btn-sm"><?php echo $text['button-back']; ?></a>
	</div>
</div>

<div class="card tickets-card">
	<!-- Ticket Info -->
	<div class="ticket-detail-header">
		<h3><?php echo htmlspecialchars($ticket['subject']); ?></h3>
		<div class="ticket-meta-grid">
			<div class="meta-item">
				<span class="meta-label"><?php echo $text['label-ticket_number']; ?></span>
				<span class="meta-value"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
			</div>
			<div class="meta-item">
				<span class="meta-label"><?php echo $text['label-priority']; ?></span>
				<span class="meta-value ticket-priority priority-<?php echo $ticket['priority']; ?>"><?php echo $text['priority-' . $ticket['priority']] ?? $ticket['priority']; ?></span>
			</div>
			<div class="meta-item">
				<span class="meta-label"><?php echo $text['label-source']; ?></span>
				<span class="meta-value"><?php echo $text['source-' . $ticket['source']] ?? $ticket['source']; ?></span>
			</div>
			<div class="meta-item">
				<span class="meta-label"><?php echo $text['label-created']; ?></span>
				<span class="meta-value"><?php echo date('Y-m-d H:i', strtotime($ticket['insert_date'])); ?></span>
			</div>
			<div class="meta-item">
				<span class="meta-label">Reporter</span>
				<span class="meta-value"><?php echo htmlspecialchars($ticket['reporter_name'] ?? 'Unknown'); ?></span>
			</div>
			<?php if ($ticket['assigned_to']) { ?>
				<div class="meta-item">
					<span class="meta-label"><?php echo $text['label-assigned_to']; ?></span>
					<span class="meta-value"><?php echo htmlspecialchars($ticket['assignee_name'] ?? '-'); ?></span>
				</div>
			<?php } ?>
		</div>
	</div>

	<!-- Description -->
	<?php if (!empty($ticket['description'])) { ?>
		<div class="ticket-description">
			<h4><?php echo $text['label-description']; ?></h4>
			<div class="description-content"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></div>
		</div>
	<?php } ?>

	<!-- Call Details (if linked to a call) -->
	<?php if (!empty($ticket['call_number'])) { ?>
		<div class="ticket-call-details">
			<h4><i class="fa-solid fa-phone"></i> <?php echo $text['label-call_details']; ?></h4>
			<div class="call-detail-grid">
				<div class="call-detail-item">
					<span class="detail-label"><?php echo $text['label-call_number']; ?></span>
					<span class="detail-value"><?php echo htmlspecialchars($ticket['call_number']); ?></span>
				</div>
				<?php if ($ticket['call_direction']) { ?>
					<div class="call-detail-item">
						<span class="detail-label"><?php echo $text['label-call_direction']; ?></span>
						<span class="detail-value"><?php echo htmlspecialchars(ucfirst($ticket['call_direction'])); ?></span>
					</div>
				<?php } ?>
				<?php if ($ticket['call_duration'] > 0) { ?>
					<div class="call-detail-item">
						<span class="detail-label"><?php echo $text['label-call_duration']; ?></span>
						<span class="detail-value"><?php echo gmdate('H:i:s', $ticket['call_duration']); ?></span>
					</div>
				<?php } ?>
				<?php if ($ticket['call_status']) { ?>
					<div class="call-detail-item">
						<span class="detail-label">Call Status</span>
						<span class="detail-value"><?php echo htmlspecialchars(ucfirst($ticket['call_status'])); ?></span>
					</div>
				<?php } ?>
				<?php if ($ticket['call_quality_mos']) { ?>
					<div class="call-detail-item">
						<span class="detail-label"><?php echo $text['label-call_quality']; ?></span>
						<span class="detail-value">MOS <?php echo number_format($ticket['call_quality_mos'], 1); ?> (<?php echo htmlspecialchars(ucfirst($ticket['call_quality_rating'])); ?>)</span>
					</div>
				<?php } ?>
				<?php if ($ticket['call_quality_issues']) { ?>
					<div class="call-detail-item">
						<span class="detail-label">Issues</span>
						<span class="detail-value text-danger"><?php echo htmlspecialchars($ticket['call_quality_issues']); ?></span>
					</div>
				<?php } ?>
				<?php if ($ticket['call_hangup_by']) { ?>
					<div class="call-detail-item">
						<span class="detail-label">Hangup</span>
						<span class="detail-value"><?php echo htmlspecialchars($ticket['call_hangup_by']); ?> <?php echo $ticket['call_hangup_cause'] ? '(' . htmlspecialchars($ticket['call_hangup_cause']) . ')' : ''; ?></span>
					</div>
				<?php } ?>
				<?php if ($ticket['call_timestamp']) { ?>
					<div class="call-detail-item">
						<span class="detail-label">Call Time</span>
						<span class="detail-value"><?php echo date('Y-m-d H:i:s', strtotime($ticket['call_timestamp'])); ?></span>
					</div>
				<?php } ?>
				<?php if ($ticket['extension']) { ?>
					<div class="call-detail-item">
						<span class="detail-label">Extension</span>
						<span class="detail-value"><?php echo htmlspecialchars($ticket['extension']); ?></span>
					</div>
				<?php } ?>
			</div>
		</div>
	<?php } ?>

	<!-- Attachments -->
	<?php if (count($attachments) > 0) { ?>
		<div class="ticket-attachments">
			<h4><i class="fa-solid fa-paperclip"></i> <?php echo $text['label-attachments']; ?></h4>
			<?php foreach ($attachments as $att) { ?>
				<div class="attachment-item">
					<span class="attachment-icon">
						<?php if ($att['attachment_type'] === 'activity_log') { ?>
							<i class="fa-solid fa-list-check"></i>
						<?php } elseif ($att['attachment_type'] === 'call_detail') { ?>
							<i class="fa-solid fa-phone-volume"></i>
						<?php } else { ?>
							<i class="fa-solid fa-file"></i>
						<?php } ?>
					</span>
					<span class="attachment-name"><?php echo htmlspecialchars($att['file_name']); ?></span>
					<span class="attachment-type">(<?php echo htmlspecialchars($att['attachment_type']); ?>)</span>
					<?php if ($att['file_type'] === 'application/json') { ?>
						<button type="button" class="btn btn-default btn-xs" onclick="toggleAttachment('att-<?php echo $att['ticket_attachment_uuid']; ?>')">
							<i class="fa-solid fa-eye"></i> View
						</button>
						<div id="att-<?php echo $att['ticket_attachment_uuid']; ?>" class="attachment-content" style="display:none;">
							<pre><?php echo htmlspecialchars($att['file_content']); ?></pre>
						</div>
					<?php } ?>
				</div>
			<?php } ?>
		</div>
	<?php } ?>

	<!-- Resolution Note -->
	<?php if (!empty($ticket['resolved_note'])) { ?>
		<div class="ticket-resolution">
			<h4><i class="fa-solid fa-check-circle"></i> <?php echo $text['label-resolution_note']; ?></h4>
			<div class="resolution-content"><?php echo nl2br(htmlspecialchars($ticket['resolved_note'])); ?></div>
		</div>
	<?php } ?>

	<!-- Replies -->
	<div class="ticket-replies">
		<h4><i class="fa-solid fa-comments"></i> <?php echo $text['label-replies']; ?> (<?php echo count($replies); ?>)</h4>
		<?php if (count($replies) > 0) { ?>
			<?php foreach ($replies as $reply) { ?>
				<div class="reply-item <?php echo $reply['is_admin'] === 't' || $reply['is_admin'] === true ? 'reply-admin' : 'reply-user'; ?>">
					<div class="reply-header">
						<span class="reply-author">
							<?php if ($reply['is_admin'] === 't' || $reply['is_admin'] === true) { ?>
								<i class="fa-solid fa-shield"></i>
							<?php } else { ?>
								<i class="fa-solid fa-user"></i>
							<?php } ?>
							<?php echo htmlspecialchars($reply['username'] ?? 'Unknown'); ?>
						</span>
						<span class="reply-date"><?php echo date('Y-m-d H:i', strtotime($reply['insert_date'])); ?></span>
					</div>
					<div class="reply-body"><?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?></div>
				</div>
			<?php } ?>
		<?php } ?>

		<!-- Reply Form -->
		<?php if (permission_exists('ticket_reply') && !in_array($ticket['status'], ['closed'])) { ?>
			<div class="reply-form">
				<form method="post">
					<input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
					<input type="hidden" name="action" value="reply">
					<div class="form-group">
						<label for="reply_text"><?php echo $text['label-add_reply']; ?></label>
						<textarea id="reply_text" name="reply_text" class="form-control" rows="4" required placeholder="Type your reply..."></textarea>
					</div>
					<button type="submit" class="btn btn-primary btn-sm"><?php echo $text['button-reply']; ?></button>
				</form>
			</div>
		<?php } ?>
	</div>

	<!-- Admin Controls -->
	<?php if (permission_exists('ticket_manage')) { ?>
		<div class="ticket-admin-controls">
			<h4><i class="fa-solid fa-cog"></i> Admin Controls</h4>
			<form method="post">
				<input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
				<input type="hidden" name="action" value="update_status">

				<div class="admin-control-grid">
					<div class="form-group">
						<label for="new_status"><?php echo $text['label-change_status']; ?></label>
						<select id="new_status" name="new_status" class="form-control">
							<option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>><?php echo $text['status-open']; ?></option>
							<option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>><?php echo $text['status-in_progress']; ?></option>
							<option value="answered" <?php echo $ticket['status'] === 'answered' ? 'selected' : ''; ?>><?php echo $text['status-answered']; ?></option>
							<option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>><?php echo $text['status-resolved']; ?></option>
							<option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>><?php echo $text['status-closed']; ?></option>
						</select>
					</div>

					<div class="form-group">
						<label for="assigned_to"><?php echo $text['label-assign']; ?></label>
						<select id="assigned_to" name="assigned_to" class="form-control">
							<option value="">-- Unassigned --</option>
							<?php foreach ($admins as $admin) { ?>
								<option value="<?php echo $admin['user_uuid']; ?>" <?php echo $ticket['assigned_to'] === $admin['user_uuid'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($admin['username']); ?>
								</option>
							<?php } ?>
						</select>
					</div>

					<div class="form-group">
						<label for="resolved_note"><?php echo $text['label-resolution_note']; ?></label>
						<textarea id="resolved_note" name="resolved_note" class="form-control" rows="2" placeholder="Optional note when resolving..."><?php echo htmlspecialchars($ticket['resolved_note'] ?? ''); ?></textarea>
					</div>
				</div>

				<button type="submit" class="btn btn-primary btn-sm"><?php echo $text['button-update']; ?></button>
			</form>
		</div>
	<?php } ?>

	<!-- Status History -->
	<?php if (count($status_log) > 0) { ?>
		<div class="ticket-status-history">
			<h4><i class="fa-solid fa-clock-rotate-left"></i> <?php echo $text['label-status_history']; ?></h4>
			<div class="status-timeline">
				<?php foreach ($status_log as $log) { ?>
					<div class="timeline-item">
						<div class="timeline-dot"></div>
						<div class="timeline-content">
							<span class="timeline-status">
								<?php echo $log['old_status'] ? htmlspecialchars($log['old_status']) . ' &rarr; ' : ''; ?>
								<strong><?php echo htmlspecialchars($log['new_status']); ?></strong>
							</span>
							<span class="timeline-user"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></span>
							<span class="timeline-date"><?php echo date('Y-m-d H:i', strtotime($log['insert_date'])); ?></span>
							<?php if (!empty($log['note'])) { ?>
								<div class="timeline-note"><?php echo htmlspecialchars($log['note']); ?></div>
							<?php } ?>
						</div>
					</div>
				<?php } ?>
			</div>
		</div>
	<?php } ?>
</div>

<script>
function toggleAttachment(id) {
	var el = document.getElementById(id);
	if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php

//include footer
	require_once "resources/footer.php";

?>
