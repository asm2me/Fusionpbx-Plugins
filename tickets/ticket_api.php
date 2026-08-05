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
	require_once __DIR__ . "/resources/classes/ticket_sms.php";

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

//verify a domain-locked ticket API key/secret pair
	function api_verify_ticket_key($api_key, $api_secret) {
		$database = new database;
		$sql = "SELECT * FROM v_ticket_api_keys WHERE api_key = :api_key AND enabled = true";
		$parameters['api_key'] = $api_key;
		$row = $database->select($sql, $parameters, 'row');
		if (!$row || !hash_equals($row['api_secret'], hash('sha256', $api_secret))) {
			return false;
		}
		$sql = "UPDATE v_ticket_api_keys SET last_used_date = now() WHERE ticket_api_key_uuid = :key_uuid";
		$database->execute($sql, ['key_uuid' => $row['ticket_api_key_uuid']]);
		return $row;
	}

//does this user have domain-wide ticket_manage-equivalent access? (mirrors the admin/superadmin grant in app_defaults.php)
	function api_user_is_ticket_manager($user_uuid) {
		$database = new database;
		$sql = "SELECT 1 FROM v_user_groups WHERE user_uuid = :user_uuid AND group_name IN ('admin', 'superadmin') LIMIT 1";
		$parameters['user_uuid'] = $user_uuid;
		return (bool) $database->select($sql, $parameters, 'column');
	}

//------- authenticate: either a domain-locked API key, or a normal browser/webphone session -------
	$api_key = '';
	$api_secret = '';
	$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
	if (!empty($auth_header)) {
		if (stripos($auth_header, 'Bearer ') === 0) {
			$parts = explode(':', substr($auth_header, 7), 2);
			$api_key = $parts[0] ?? '';
			$api_secret = $parts[1] ?? '';
		} else {
			$api_key = trim($auth_header);
			$api_secret = $_SERVER['HTTP_X_API_SECRET'] ?? '';
		}
	}
	if (empty($api_key)) {
		$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
		$api_secret = $_GET['api_secret'] ?? $_POST['api_secret'] ?? '';
	}

	$is_api_key_auth = false;

	if (!empty($api_key) || !empty($api_secret)) {
		//API-key auth: locked to the single domain the key was issued for; create + view only
		$key_row = api_verify_ticket_key($api_key, $api_secret);
		if (!$key_row) {
			http_response_code(401);
			echo json_encode(['error' => 'invalid_api_key']);
			exit;
		}
		if (!in_array($action, ['create', 'list', 'detail', 'updates'])) {
			http_response_code(403);
			echo json_encode(['error' => 'action_not_permitted_for_api_key']);
			exit;
		}
		$domain_uuid = $key_row['domain_uuid'];
		$acting_user_uuid = $key_row['user_uuid'];
		$is_api_key_auth = true;
		//visibility follows the key owner's real role, same as if they were logged in
		$is_ticket_manager = api_user_is_ticket_manager($acting_user_uuid);
	} else {
		require_once "resources/check_auth.php";
		if (!permission_exists('ticket_api') && !permission_exists('ticket_view')) {
			http_response_code(403);
			echo json_encode(['error' => 'access_denied']);
			exit;
		}
		$domain_uuid = $_SESSION['domain_uuid'];
		$acting_user_uuid = $_SESSION['user_uuid'];
		$is_ticket_manager = permission_exists('ticket_manage');
	}

// ====== CREATE TICKET ======
	if ($action === 'create' && $method === 'POST') {
		if (!$is_api_key_auth && !permission_exists('ticket_add')) {
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
		$contact_name = trim($input['contact_name'] ?? '');
		$contact_phone = trim($input['contact_phone'] ?? '');
		$contact_email = trim($input['contact_email'] ?? '');

		if (empty($subject)) {
			http_response_code(400);
			echo json_encode(['error' => 'subject_required']);
			exit;
		}

		if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) $priority = 'normal';
		if (!in_array($source, ['panel', 'webphone', 'dialer'])) $source = 'panel';

		$ticket_uuid = uuid();
		$ticket_number = api_generate_ticket_number($domain_uuid);

		//call details are only meaningful together with a call_number
		$has_call = !empty($input['call_number']);
		$has_quality = $has_call && !empty($input['call_quality_mos']);
		$has_hangup = $has_call && !empty($input['call_hangup_by']);

		$sql  = "INSERT INTO v_tickets ";
		$sql .= "(ticket_uuid, domain_uuid, user_uuid, ticket_number, subject, description, status, priority, source, ";
		$sql .= "call_number, call_direction, call_duration, call_status, call_timestamp, extension, ";
		$sql .= "call_quality_mos, call_quality_rating, call_quality_issues, call_hangup_by, call_hangup_cause, ";
		$sql .= "contact_name, contact_phone, contact_email, ";
		$sql .= "insert_date, insert_user) ";
		$sql .= "VALUES (:ticket_uuid, :domain_uuid, :user_uuid, :ticket_number, :subject, :description, 'open', :priority, :source, ";
		$sql .= ":call_number, :call_direction, :call_duration, :call_status, :call_timestamp, :extension, ";
		$sql .= ":call_quality_mos, :call_quality_rating, :call_quality_issues, :call_hangup_by, :call_hangup_cause, ";
		$sql .= ":contact_name, :contact_phone, :contact_email, ";
		$sql .= "now(), :insert_user)";
		$parameters = [
			'ticket_uuid' => $ticket_uuid,
			'domain_uuid' => $domain_uuid,
			'user_uuid' => $acting_user_uuid,
			'ticket_number' => $ticket_number,
			'subject' => $subject,
			'description' => $description,
			'priority' => $priority,
			'source' => $source,
			'call_number' => $has_call ? $input['call_number'] : null,
			'call_direction' => $has_call ? ($input['call_direction'] ?? '') : null,
			'call_duration' => $has_call ? intval($input['call_duration'] ?? 0) : null,
			'call_status' => $has_call ? ($input['call_status'] ?? '') : null,
			'call_timestamp' => $has_call ? ($input['call_timestamp'] ?? null) : null,
			'extension' => $has_call ? ($input['extension'] ?? '') : null,
			'call_quality_mos' => $has_quality ? floatval($input['call_quality_mos']) : null,
			'call_quality_rating' => $has_quality ? ($input['call_quality_rating'] ?? '') : null,
			'call_quality_issues' => $has_quality ? ($input['call_quality_issues'] ?? '') : null,
			'call_hangup_by' => $has_hangup ? $input['call_hangup_by'] : null,
			'call_hangup_cause' => $has_hangup ? ($input['call_hangup_cause'] ?? '') : null,
			'contact_name' => $contact_name ?: null,
			'contact_phone' => $contact_phone ?: null,
			'contact_email' => $contact_email ?: null,
			'insert_user' => $acting_user_uuid,
		];

		$database = new database;
		$database->execute($sql, $parameters);
		unset($sql, $parameters);

		//confirm the ticket actually landed before reporting success
		$saved = $database->select("SELECT 1 FROM v_tickets WHERE ticket_uuid = :ticket_uuid", ['ticket_uuid' => $ticket_uuid], 'column');
		if (!$saved) {
			http_response_code(500);
			echo json_encode(['error' => 'ticket_not_saved']);
			exit;
		}

		//save activity log attachment
		if (!empty($input['activity_log'])) {
			$log_data = is_array($input['activity_log']) ? json_encode($input['activity_log']) : $input['activity_log'];
			$att_uuid = uuid();
			$sql  = "INSERT INTO v_ticket_attachments ";
			$sql .= "(ticket_attachment_uuid, ticket_uuid, domain_uuid, file_name, file_type, file_content, attachment_type, insert_date, insert_user) ";
			$sql .= "VALUES (:att_uuid, :ticket_uuid, :domain_uuid, 'activity_log.json', 'application/json', :content, 'activity_log', now(), :user_uuid)";
			$parameters['att_uuid'] = $att_uuid;
			$parameters['ticket_uuid'] = $ticket_uuid;
			$parameters['domain_uuid'] = $domain_uuid;
			$parameters['content'] = $log_data;
			$parameters['user_uuid'] = $acting_user_uuid;
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
			$parameters['domain_uuid'] = $domain_uuid;
			$parameters['content'] = $detail_data;
			$parameters['user_uuid'] = $acting_user_uuid;
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
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['user_uuid'] = $acting_user_uuid;
		$database->execute($sql, $parameters);
		unset($sql, $parameters);

		//best-effort SMS notification; never blocks ticket creation
		$sms = new ticket_sms($domain_uuid);
		$sms->notify([
			'ticket_number' => $ticket_number,
			'subject' => $subject,
			'status' => 'open',
			'call_number' => $has_call ? $input['call_number'] : '',
			'contact_phone' => $contact_phone,
		], 'created');

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
		$parameters['domain_uuid'] = $domain_uuid;

		//visibility follows the acting user's real role, whether they came in via session or API key
		if (!$is_ticket_manager) {
			$sql .= "AND user_uuid = :user_uuid ";
			$parameters['user_uuid'] = $acting_user_uuid;
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
		//accept either the internal id (uuid) or the human-readable ticket_number (e.g. TKT-00042)
		$ticket_uuid = $_GET['id'] ?? '';
		$ticket_number_lookup = trim($_GET['ticket_number'] ?? '');

		if (!empty($ticket_uuid) && !is_uuid($ticket_uuid)) {
			http_response_code(400);
			echo json_encode(['error' => 'invalid_id']);
			exit;
		}
		if (empty($ticket_uuid) && empty($ticket_number_lookup)) {
			http_response_code(400);
			echo json_encode(['error' => 'invalid_id']);
			exit;
		}

		$sql  = "SELECT * FROM v_tickets WHERE domain_uuid = :domain_uuid";
		$parameters['domain_uuid'] = $domain_uuid;

		if (!empty($ticket_uuid)) {
			$sql .= " AND ticket_uuid = :ticket_uuid";
			$parameters['ticket_uuid'] = $ticket_uuid;
		} else {
			$sql .= " AND ticket_number = :ticket_number";
			$parameters['ticket_number'] = $ticket_number_lookup;
		}

		if (!$is_ticket_manager) {
			$sql .= " AND user_uuid = :user_uuid";
			$parameters['user_uuid'] = $acting_user_uuid;
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
		$ticket_uuid = $ticket['ticket_uuid'];
		$sql = "SELECT r.*, u.username FROM v_ticket_replies r LEFT JOIN v_users u ON u.user_uuid = r.user_uuid WHERE r.ticket_uuid = :ticket_uuid AND r.domain_uuid = :domain_uuid ORDER BY r.insert_date ASC";
		$parameters['ticket_uuid'] = $ticket_uuid;
		$parameters['domain_uuid'] = $domain_uuid;
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
		$parameters['domain_uuid'] = $domain_uuid;

		//visibility follows the acting user's real role, whether they came in via session or API key
		if (!$is_ticket_manager) {
			$sql .= "AND t.user_uuid = :user_uuid ";
			$parameters['user_uuid'] = $acting_user_uuid;
		}

		$sql .= "AND l.insert_date > :since ";
		$sql .= "AND l.new_status IN ('answered', 'resolved', 'closed') ";
		$sql .= "ORDER BY l.insert_date DESC LIMIT 20";
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
		$sql = "SELECT status, user_uuid, ticket_number, subject, call_number, contact_phone FROM v_tickets WHERE ticket_uuid = :ticket_uuid AND domain_uuid = :domain_uuid";
		$parameters['ticket_uuid'] = $ticket_uuid;
		$parameters['domain_uuid'] = $domain_uuid;
		if (!$is_ticket_manager) {
			$sql .= " AND user_uuid = :user_uuid";
			$parameters['user_uuid'] = $acting_user_uuid;
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
		$is_admin = $is_ticket_manager ? 'true' : 'false';

		$sql  = "INSERT INTO v_ticket_replies ";
		$sql .= "(ticket_reply_uuid, ticket_uuid, domain_uuid, user_uuid, reply_text, is_admin, insert_date, insert_user) ";
		$sql .= "VALUES (:reply_uuid, :ticket_uuid, :domain_uuid, :user_uuid, :reply_text, :is_admin, now(), :user_uuid)";
		$parameters['reply_uuid'] = $reply_uuid;
		$parameters['ticket_uuid'] = $ticket_uuid;
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['user_uuid'] = $acting_user_uuid;
		$parameters['reply_text'] = $reply_text;
		$parameters['is_admin'] = $is_admin;
		$database->execute($sql, $parameters);
		unset($sql, $parameters);

		//best-effort SMS notification; never blocks the reply
		$sms = new ticket_sms($domain_uuid);
		$sms->notify([
			'ticket_number' => $ticket['ticket_number'],
			'subject' => $ticket['subject'],
			'status' => $ticket['status'],
			'call_number' => $ticket['call_number'] ?? '',
			'contact_phone' => $ticket['contact_phone'] ?? '',
		], 'updated');

		echo json_encode(['status' => 'success', 'reply_uuid' => $reply_uuid]);
		exit;
	}

//unknown action
	http_response_code(400);
	echo json_encode(['error' => 'unknown_action']);

?>
