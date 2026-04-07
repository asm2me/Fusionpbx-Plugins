<?php

/*
	FusionPBX - Ticket API
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	REST API for ticket management from web phone and mobile dialers.

	Endpoints:
	  POST   ?action=create     - Create a new ticket (with call details & activity log)
	  GET    ?action=list       - List user's tickets
	  GET    ?action=detail&id= - Get ticket detail with replies
	  GET    ?action=updates    - Get ticket status updates (for webphone polling)
	  POST   ?action=reply&id=  - Add a reply to a ticket
*/

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('ticket_api') && !permission_exists('ticket_view')) {
		header('Content-Type: application/json');
		http_response_code(403);
		echo json_encode(['error' => 'access_denied']);
		exit;
	}

	header('Content-Type: application/json');

	$action = $_GET['action'] ?? $_POST['action'] ?? '';
	$method = $_SERVER['REQUEST_METHOD'];

//generate ticket number
	function api_generate_ticket_number($domain_uuid) {
		$database = new database;
		$sql = "SELECT count(*) + 1 FROM v_tickets WHERE domain_uuid = :domain_uuid";
		$parameters['domain_uuid'] = $domain_uuid;
		$next = $database->select($sql, $parameters, 'column') ?: 1;
		return 'TKT-' . str_pad($next, 5, '0', STR_PAD_LEFT);
	}

// ====== CREATE TICKET ======
	if ($action === 'create' && $method === 'POST') {
		if (!permission_exists('ticket_add')) {
			http_response_code(403);
			echo json_encode(['error' => 'permission_denied']);
			exit;
		}

		//accept JSON body or form-encoded
		$input = json_decode(file_get_contents('php://input'), true);
		if (empty($input)) $input = $_POST;

		$subject = trim($input['subject'] ?? '');
		$description = trim($input['description'] ?? '');
		$priority = $input['priority'] ?? 'normal';
		$source = $input['source'] ?? 'panel';

		if (empty($subject)) {
			http_response_code(400);
			echo json_encode(['error' => 'subject_required']);
			exit;
		}

		if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) $priority = 'normal';
		if (!in_array($source, ['panel', 'webphone', 'dialer'])) $source = 'panel';

		$ticket_uuid = uuid();
		$ticket_number = api_generate_ticket_number($_SESSION['domain_uuid']);

		$array['tickets'][0]['ticket_uuid'] = $ticket_uuid;
		$array['tickets'][0]['domain_uuid'] = $_SESSION['domain_uuid'];
		$array['tickets'][0]['user_uuid'] = $_SESSION['user_uuid'];
		$array['tickets'][0]['ticket_number'] = $ticket_number;
		$array['tickets'][0]['subject'] = $subject;
		$array['tickets'][0]['description'] = $description;
		$array['tickets'][0]['status'] = 'open';
		$array['tickets'][0]['priority'] = $priority;
		$array['tickets'][0]['source'] = $source;

		//call details
		if (!empty($input['call_number'])) {
			$array['tickets'][0]['call_number'] = $input['call_number'];
			$array['tickets'][0]['call_direction'] = $input['call_direction'] ?? '';
			$array['tickets'][0]['call_duration'] = intval($input['call_duration'] ?? 0);
			$array['tickets'][0]['call_status'] = $input['call_status'] ?? '';
			$array['tickets'][0]['call_timestamp'] = $input['call_timestamp'] ?? null;
			$array['tickets'][0]['extension'] = $input['extension'] ?? '';

			if (!empty($input['call_quality_mos'])) {
				$array['tickets'][0]['call_quality_mos'] = floatval($input['call_quality_mos']);
				$array['tickets'][0]['call_quality_rating'] = $input['call_quality_rating'] ?? '';
				$array['tickets'][0]['call_quality_issues'] = $input['call_quality_issues'] ?? '';
			}
			if (!empty($input['call_hangup_by'])) {
				$array['tickets'][0]['call_hangup_by'] = $input['call_hangup_by'];
				$array['tickets'][0]['call_hangup_cause'] = $input['call_hangup_cause'] ?? '';
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

		//save activity log attachment
		if (!empty($input['activity_log'])) {
			$log_data = is_array($input['activity_log']) ? json_encode($input['activity_log']) : $input['activity_log'];
			$att_uuid = uuid();
			$sql  = "INSERT INTO v_ticket_attachments ";
			$sql .= "(ticket_attachment_uuid, ticket_uuid, domain_uuid, file_name, file_type, file_content, attachment_type, insert_date, insert_user) ";
			$sql .= "VALUES (:att_uuid, :ticket_uuid, :domain_uuid, 'activity_log.json', 'application/json', :content, 'activity_log', now(), :user_uuid)";
			$parameters['att_uuid'] = $att_uuid;
			$parameters['ticket_uuid'] = $ticket_uuid;
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$parameters['content'] = $log_data;
			$parameters['user_uuid'] = $_SESSION['user_uuid'];
			$database->execute($sql, $parameters);
			unset($sql, $parameters);
		}

		//save call detail attachment
		if (!empty($input['call_detail_json'])) {
			$detail_data = is_array($input['call_detail_json']) ? json_encode($input['call_detail_json']) : $input['call_detail_json'];
			$att_uuid = uuid();
			$sql  = "INSERT INTO v_ticket_attachments ";
			$sql .= "(ticket_attachment_uuid, ticket_uuid, domain_uuid, file_name, file_type, file_content, attachment_type, insert_date, insert_user) ";
			$sql .= "VALUES (:att_uuid, :ticket_uuid, :domain_uuid, 'call_details.json', 'application/json', :content, 'call_detail', now(), :user_uuid)";
			$parameters['att_uuid'] = $att_uuid;
			$parameters['ticket_uuid'] = $ticket_uuid;
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$parameters['content'] = $detail_data;
			$parameters['user_uuid'] = $_SESSION['user_uuid'];
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

		echo json_encode([
			'status' => 'success',
			'ticket_uuid' => $ticket_uuid,
			'ticket_number' => $ticket_number
		]);
		exit;
	}

// ====== LIST TICKETS ======
	if ($action === 'list' && $method === 'GET') {
		$status_filter = $_GET['status'] ?? '';
		$limit = intval($_GET['limit'] ?? 50);
		$offset = intval($_GET['offset'] ?? 0);
		if ($limit > 100) $limit = 100;
		if ($limit < 1) $limit = 50;

		$sql  = "SELECT ticket_uuid, ticket_number, subject, status, priority, source, ";
		$sql .= "call_number, call_direction, extension, insert_date, update_date ";
		$sql .= "FROM v_tickets WHERE domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];

		if (!permission_exists('ticket_manage')) {
			$sql .= "AND user_uuid = :user_uuid ";
			$parameters['user_uuid'] = $_SESSION['user_uuid'];
		}

		if (!empty($status_filter) && in_array($status_filter, ['open', 'in_progress', 'answered', 'resolved', 'closed'])) {
			$sql .= "AND status = :status ";
			$parameters['status'] = $status_filter;
		}

		$sql .= "ORDER BY insert_date DESC LIMIT :limit OFFSET :offset";
		$parameters['limit'] = $limit;
		$parameters['offset'] = $offset;

		$database = new database;
		$tickets = $database->select($sql, $parameters, 'all') ?: [];
		unset($sql, $parameters);

		echo json_encode(['tickets' => $tickets]);
		exit;
	}

// ====== TICKET DETAIL ======
	if ($action === 'detail' && $method === 'GET') {
		$ticket_uuid = $_GET['id'] ?? '';
		if (!is_uuid($ticket_uuid)) {
			http_response_code(400);
			echo json_encode(['error' => 'invalid_id']);
			exit;
		}

		$sql  = "SELECT * FROM v_tickets WHERE ticket_uuid = :ticket_uuid AND domain_uuid = :domain_uuid";
		$parameters['ticket_uuid'] = $ticket_uuid;
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];

		if (!permission_exists('ticket_manage')) {
			$sql .= " AND user_uuid = :user_uuid";
			$parameters['user_uuid'] = $_SESSION['user_uuid'];
		}

		$database = new database;
		$ticket = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (empty($ticket)) {
			http_response_code(404);
			echo json_encode(['error' => 'not_found']);
			exit;
		}

		//load replies
		$sql = "SELECT r.*, u.username FROM v_ticket_replies r LEFT JOIN v_users u ON u.user_uuid = r.user_uuid WHERE r.ticket_uuid = :ticket_uuid AND r.domain_uuid = :domain_uuid ORDER BY r.insert_date ASC";
		$parameters['ticket_uuid'] = $ticket_uuid;
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$replies = $database->select($sql, $parameters, 'all') ?: [];
		unset($sql, $parameters);

		echo json_encode(['ticket' => $ticket, 'replies' => $replies]);
		exit;
	}

// ====== TICKET UPDATES (for webphone polling) ======
	if ($action === 'updates' && $method === 'GET') {
		//returns tickets that changed status since a given timestamp
		$since = $_GET['since'] ?? '';
		if (empty($since)) {
			//default: last 24 hours
			$since = date('Y-m-d H:i:s', time() - 86400);
		}

		$sql  = "SELECT t.ticket_uuid, t.ticket_number, t.subject, t.status, t.call_number, ";
		$sql .= "t.resolved_note, t.update_date, l.old_status, l.new_status, l.note AS status_note ";
		$sql .= "FROM v_tickets t ";
		$sql .= "JOIN v_ticket_status_log l ON l.ticket_uuid = t.ticket_uuid ";
		$sql .= "WHERE t.domain_uuid = :domain_uuid ";
		$sql .= "AND t.user_uuid = :user_uuid ";
		$sql .= "AND l.insert_date > :since ";
		$sql .= "AND l.new_status IN ('answered', 'resolved', 'closed') ";
		$sql .= "ORDER BY l.insert_date DESC LIMIT 20";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['user_uuid'] = $_SESSION['user_uuid'];
		$parameters['since'] = $since;

		$database = new database;
		$updates = $database->select($sql, $parameters, 'all') ?: [];
		unset($sql, $parameters);

		echo json_encode(['updates' => $updates, 'timestamp' => date('Y-m-d H:i:s')]);
		exit;
	}

// ====== REPLY TO TICKET ======
	if ($action === 'reply' && $method === 'POST') {
		if (!permission_exists('ticket_reply')) {
			http_response_code(403);
			echo json_encode(['error' => 'permission_denied']);
			exit;
		}

		$ticket_uuid = $_GET['id'] ?? '';
		$input = json_decode(file_get_contents('php://input'), true);
		if (empty($input)) $input = $_POST;

		$reply_text = trim($input['reply_text'] ?? '');

		if (!is_uuid($ticket_uuid) || empty($reply_text)) {
			http_response_code(400);
			echo json_encode(['error' => 'invalid_input']);
			exit;
		}

		//verify ticket exists and user can access it
		$sql = "SELECT status, user_uuid FROM v_tickets WHERE ticket_uuid = :ticket_uuid AND domain_uuid = :domain_uuid";
		$parameters['ticket_uuid'] = $ticket_uuid;
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		if (!permission_exists('ticket_manage')) {
			$sql .= " AND user_uuid = :user_uuid";
			$parameters['user_uuid'] = $_SESSION['user_uuid'];
		}

		$database = new database;
		$ticket = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (empty($ticket)) {
			http_response_code(404);
			echo json_encode(['error' => 'not_found']);
			exit;
		}

		if ($ticket['status'] === 'closed') {
			http_response_code(400);
			echo json_encode(['error' => 'ticket_closed']);
			exit;
		}

		$reply_uuid = uuid();
		$is_admin = permission_exists('ticket_manage') ? 'true' : 'false';

		$sql  = "INSERT INTO v_ticket_replies ";
		$sql .= "(ticket_reply_uuid, ticket_uuid, domain_uuid, user_uuid, reply_text, is_admin, insert_date, insert_user) ";
		$sql .= "VALUES (:reply_uuid, :ticket_uuid, :domain_uuid, :user_uuid, :reply_text, :is_admin, now(), :user_uuid)";
		$parameters['reply_uuid'] = $reply_uuid;
		$parameters['ticket_uuid'] = $ticket_uuid;
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['user_uuid'] = $_SESSION['user_uuid'];
		$parameters['reply_text'] = $reply_text;
		$parameters['is_admin'] = $is_admin;
		$database->execute($sql, $parameters);
		unset($sql, $parameters);

		echo json_encode(['status' => 'success', 'reply_uuid' => $reply_uuid]);
		exit;
	}

//unknown action
	http_response_code(400);
	echo json_encode(['error' => 'unknown_action']);

?>
