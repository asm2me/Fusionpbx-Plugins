<?php

/*
	FusionPBX - New Support Ticket
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1
*/

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('ticket_add')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//generate ticket number
	function generate_ticket_number($domain_uuid) {
		$database = new database;
		$sql = "SELECT count(*) + 1 FROM v_tickets WHERE domain_uuid = :domain_uuid";
		$parameters['domain_uuid'] = $domain_uuid;
		$next = $database->select($sql, $parameters, 'column') ?: 1;
		return 'TKT-' . str_pad($next, 5, '0', STR_PAD_LEFT);
	}

//create token
	$token = new token;
	$token_hash = $token->create($_SERVER['PHP_SELF']);

	//process form submission
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		//validate token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			$_SESSION['message'] = "Invalid token.";
			header("Location: ticket_new.php");
			exit;
		}

		$subject = trim($_POST['subject'] ?? '');
		$description = trim($_POST['description'] ?? '');
		$priority = $_POST['priority'] ?? 'normal';

		if (empty($subject)) {
			$_SESSION['message'] = "Subject is required.";
			header("Location: ticket_new.php");
			exit;
		}

		//sanitize priority
		if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) $priority = 'normal';

		$ticket_uuid = uuid();
		$ticket_number = generate_ticket_number($_SESSION['domain_uuid']);

		$array['tickets'][0]['ticket_uuid'] = $ticket_uuid;
		$array['tickets'][0]['domain_uuid'] = $_SESSION['domain_uuid'];
		$array['tickets'][0]['user_uuid'] = $_SESSION['user_uuid'];
		$array['tickets'][0]['ticket_number'] = $ticket_number;
		$array['tickets'][0]['subject'] = $subject;
		$array['tickets'][0]['description'] = $description;
		$array['tickets'][0]['status'] = 'open';
		$array['tickets'][0]['priority'] = $priority;
		$array['tickets'][0]['source'] = 'panel';

		//call details (if provided via hidden fields from webphone redirect)
		if (!empty($_POST['call_number'])) {
			$array['tickets'][0]['call_number'] = $_POST['call_number'];
			$array['tickets'][0]['call_direction'] = $_POST['call_direction'] ?? '';
			$array['tickets'][0]['call_duration'] = intval($_POST['call_duration'] ?? 0);
			$array['tickets'][0]['call_status'] = $_POST['call_status'] ?? '';
			$array['tickets'][0]['call_timestamp'] = $_POST['call_timestamp'] ?? null;
			$array['tickets'][0]['source'] = $_POST['source'] ?? 'panel';
			$array['tickets'][0]['extension'] = $_POST['extension'] ?? '';
			if (!empty($_POST['call_quality_mos'])) {
				$array['tickets'][0]['call_quality_mos'] = floatval($_POST['call_quality_mos']);
				$array['tickets'][0]['call_quality_rating'] = $_POST['call_quality_rating'] ?? '';
				$array['tickets'][0]['call_quality_issues'] = $_POST['call_quality_issues'] ?? '';
			}
			if (!empty($_POST['call_hangup_by'])) {
				$array['tickets'][0]['call_hangup_by'] = $_POST['call_hangup_by'];
				$array['tickets'][0]['call_hangup_cause'] = $_POST['call_hangup_cause'] ?? '';
			}
		}

		$p = new permissions;
		$p->add("ticket_add", "temp");

		$database = new database;
		$database->app_name = "tickets";
		$database->app_uuid = "a1b2c3d4-e5f6-7890-abcd-ef1234567890";
		$database->save($array);
		unset($array);

		$p->delete("ticket_add", "temp");

		//save activity log attachment if provided
		if (!empty($_POST['activity_log'])) {
			$att_uuid = uuid();
			$sql  = "INSERT INTO v_ticket_attachments ";
			$sql .= "(ticket_attachment_uuid, ticket_uuid, domain_uuid, file_name, file_type, file_content, attachment_type, insert_date, insert_user) ";
			$sql .= "VALUES (:att_uuid, :ticket_uuid, :domain_uuid, :file_name, :file_type, :file_content, :att_type, now(), :user_uuid)";
			$parameters['att_uuid'] = $att_uuid;
			$parameters['ticket_uuid'] = $ticket_uuid;
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$parameters['file_name'] = 'activity_log.json';
			$parameters['file_type'] = 'application/json';
			$parameters['file_content'] = $_POST['activity_log'];
			$parameters['att_type'] = 'activity_log';
			$parameters['user_uuid'] = $_SESSION['user_uuid'];
			$database = new database;
			$database->execute($sql, $parameters);
			unset($sql, $parameters);
		}

		//save call detail attachment if provided
		if (!empty($_POST['call_detail_json'])) {
			$att_uuid = uuid();
			$sql  = "INSERT INTO v_ticket_attachments ";
			$sql .= "(ticket_attachment_uuid, ticket_uuid, domain_uuid, file_name, file_type, file_content, attachment_type, insert_date, insert_user) ";
			$sql .= "VALUES (:att_uuid, :ticket_uuid, :domain_uuid, :file_name, :file_type, :file_content, :att_type, now(), :user_uuid)";
			$parameters['att_uuid'] = $att_uuid;
			$parameters['ticket_uuid'] = $ticket_uuid;
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$parameters['file_name'] = 'call_details.json';
			$parameters['file_type'] = 'application/json';
			$parameters['file_content'] = $_POST['call_detail_json'];
			$parameters['att_type'] = 'call_detail';
			$parameters['user_uuid'] = $_SESSION['user_uuid'];
			$database = new database;
			$database->execute($sql, $parameters);
			unset($sql, $parameters);
		}

		//log initial status
		$log_uuid = uuid();
		$sql  = "INSERT INTO v_ticket_status_log ";
		$sql .= "(ticket_status_log_uuid, ticket_uuid, domain_uuid, old_status, new_status, changed_by, insert_date) ";
		$sql .= "VALUES (:log_uuid, :ticket_uuid, :domain_uuid, NULL, 'open', :user_uuid, now())";
		$parameters['log_uuid'] = $log_uuid;
		$parameters['ticket_uuid'] = $ticket_uuid;
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['user_uuid'] = $_SESSION['user_uuid'];
		$database->execute($sql, $parameters);
		unset($sql, $parameters);

		$_SESSION['message'] = $text['message-ticket_created'];
		header("Location: ticket_detail.php?id=" . urlencode($ticket_uuid));
		exit;
	}

//include header
	$document['title'] = $text['title-new_ticket'];
	require_once "resources/header.php";

?>

<link rel="stylesheet" href="/app/tickets/resources/css/tickets.css">

<div class="action_bar" id="action_bar">
	<div class="heading"><b><?php echo $text['title-new_ticket']; ?></b></div>
	<div class="actions">
		<a href="tickets.php" class="btn btn-default btn-sm"><?php echo $text['button-back']; ?></a>
	</div>
</div>

<div class="card tickets-card">
	<form method="post" id="ticket-form">
		<input type="hidden" name="<?php echo $token['name']; ?>" value="<?php echo $token_hash['hash']; ?>">

		<div class="form-group">
			<label for="subject"><?php echo $text['label-subject']; ?> <span class="required">*</span></label>
			<input type="text" id="subject" name="subject" class="form-control" required maxlength="255" autofocus>
		</div>

		<div class="form-group">
			<label for="priority"><?php echo $text['label-priority']; ?></label>
			<select id="priority" name="priority" class="form-control" style="width:200px;">
				<option value="low"><?php echo $text['priority-low']; ?></option>
				<option value="normal" selected><?php echo $text['priority-normal']; ?></option>
				<option value="high"><?php echo $text['priority-high']; ?></option>
				<option value="urgent"><?php echo $text['priority-urgent']; ?></option>
			</select>
		</div>

		<div class="form-group">
			<label for="description"><?php echo $text['label-description']; ?></label>
			<textarea id="description" name="description" class="form-control" rows="6" placeholder="Describe the issue in detail..."></textarea>
		</div>

		<!-- Hidden fields populated by webphone report -->
		<input type="hidden" name="source" value="panel">
		<input type="hidden" name="call_number" value="">
		<input type="hidden" name="call_direction" value="">
		<input type="hidden" name="call_duration" value="">
		<input type="hidden" name="call_status" value="">
		<input type="hidden" name="call_timestamp" value="">
		<input type="hidden" name="extension" value="">
		<input type="hidden" name="call_quality_mos" value="">
		<input type="hidden" name="call_quality_rating" value="">
		<input type="hidden" name="call_quality_issues" value="">
		<input type="hidden" name="call_hangup_by" value="">
		<input type="hidden" name="call_hangup_cause" value="">
		<input type="hidden" name="activity_log" value="">
		<input type="hidden" name="call_detail_json" value="">

		<div class="form-actions">
			<button type="submit" class="btn btn-primary"><?php echo $text['button-submit']; ?></button>
			<a href="tickets.php" class="btn btn-default"><?php echo $text['button-cancel']; ?></a>
		</div>
	</form>
</div>

<?php

//include footer
	require_once "resources/footer.php";

?>
