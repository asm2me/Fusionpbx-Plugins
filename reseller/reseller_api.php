<?php

/**
 * Reseller REST API endpoint
 *
 * Accepts API key authentication via Authorization header or query parameters.
 * Routes requests to the reseller_api class.
 * Returns JSON responses.
 */

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";

//set JSON content type
	header('Content-Type: application/json');

//include the reseller classes
	require_once __DIR__ . "/resources/classes/reseller.php";
	require_once __DIR__ . "/resources/classes/reseller_api.php";

//get authentication credentials
	$api_key = '';
	$api_secret = '';

	//check Authorization header (Bearer token format: "api_key:api_secret")
	$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
	if (!empty($auth_header)) {
		if (strpos($auth_header, 'Bearer ') === 0) {
			$token_string = substr($auth_header, 7);
			$parts = explode(':', $token_string, 2);
			$api_key = $parts[0] ?? '';
			$api_secret = $parts[1] ?? '';
		} elseif (strpos($auth_header, 'Basic ') === 0) {
			$decoded = base64_decode(substr($auth_header, 6));
			$parts = explode(':', $decoded, 2);
			$api_key = $parts[0] ?? '';
			$api_secret = $parts[1] ?? '';
		}
	}

	//fallback to query parameters
	if (empty($api_key)) {
		$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
		$api_secret = $_GET['api_secret'] ?? $_POST['api_secret'] ?? '';
	}

//authenticate
	$api = new reseller_api;

	if (empty($api_key) || empty($api_secret)) {
		http_response_code(401);
		echo json_encode(['error' => 'API key and secret are required. Use Authorization: Bearer api_key:api_secret']);
		exit;
	}

	if (!$api->authenticate($api_key, $api_secret)) {
		http_response_code(401);
		echo json_encode(['error' => 'Authentication failed. Invalid API key or secret.']);
		exit;
	}

//determine the endpoint from the request URI
	$request_uri = $_SERVER['REQUEST_URI'] ?? '';
	$base_path = '/app/reseller/reseller_api.php';

	//extract the path after the base
	$endpoint = '';
	$pos = strpos($request_uri, $base_path);
	if ($pos !== false) {
		$endpoint = substr($request_uri, $pos + strlen($base_path));
		//remove query string
		$qpos = strpos($endpoint, '?');
		if ($qpos !== false) {
			$endpoint = substr($endpoint, 0, $qpos);
		}
	}

	//also support endpoint as a query parameter
	if (empty($endpoint) || $endpoint === '' || $endpoint === '/') {
		$endpoint = $_GET['endpoint'] ?? $_POST['endpoint'] ?? '/';
	}

	//ensure endpoint starts with /
	if (strpos($endpoint, '/') !== 0) {
		$endpoint = '/' . $endpoint;
	}

//get HTTP method
	$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

//get request data
	$data = [];
	if ($method === 'GET') {
		$data = $_GET;
		unset($data['api_key'], $data['api_secret'], $data['endpoint']);
	} else {
		$input = file_get_contents('php://input');
		if (!empty($input)) {
			$json_data = json_decode($input, true);
			if (is_array($json_data)) {
				$data = $json_data;
			}
		}
		if (empty($data)) {
			$data = $_POST;
			unset($data['api_key'], $data['api_secret'], $data['endpoint']);
		}
	}

//handle the request
	$response = $api->handle_request($method, $endpoint, $data);

//send the response
	$status = $response['status'] ?? 200;
	http_response_code($status);
	echo json_encode($response['body'], JSON_PRETTY_PRINT);

?>
