<?php
/**
 * POST /token.php
 *
 * Called from the CLIENT'S BACKEND, never from the browser. This is
 * why it's safe to include client_secret here. Two grant types:
 *
 *   grant_type=authorization_code
 *     client_id, client_secret, code, redirect_uri
 *
 *   grant_type=refresh_token
 *     client_id, client_secret, refresh_token
 */

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$grantType = $_POST['grant_type'] ?? '';
$clientId = $_POST['client_id'] ?? '';
$clientSecret = $_POST['client_secret'] ?? '';

$client = $clientId ? find_client($clientId) : null;
if (!$client || !hash_equals($client['client_secret'], $clientSecret)) {
    json_response(['error' => 'invalid_client'], 401);
}

if ($grantType === 'authorization_code') {
    $code = $_POST['code'] ?? '';
    $redirectUri = $_POST['redirect_uri'] ?? '';

    if (!$code || !$redirectUri) {
        json_response(['error' => 'invalid_request', 'message' => 'code and redirect_uri are required'], 400);
    }

    $result = redeem_auth_code($code, $clientId, $redirectUri);
    if (!is_int($result)) {
        json_response(['error' => $result], 400);
    }

    json_response(issue_tokens($result, $clientId));
}

if ($grantType === 'refresh_token') {
    $refreshToken = $_POST['refresh_token'] ?? '';
    if (!$refreshToken) {
        json_response(['error' => 'invalid_request', 'message' => 'refresh_token is required'], 400);
    }

    $result = refresh_access_token($refreshToken, $clientId);
    if (!is_array($result)) {
        json_response(['error' => $result], 400);
    }

    json_response($result);
}

json_response(['error' => 'unsupported_grant_type'], 400);
