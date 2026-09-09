<?php
/**
 * GET /userinfo.php
 * Header: Authorization: Bearer <access_token>
 *
 * Called from the client's backend after it has an access token.
 * Returns only what a client app needs to know about the user,
 * never the password hash, never anything internal.
 */

require_once __DIR__ . '/includes/auth.php';

$header = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']  // some Apache/PHP-FPM setups strip Authorization unless redirected
    ?? '';

if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
    json_response(['error' => 'missing_token'], 401);
}

$user = user_for_access_token(trim($m[1]));
if (!$user) {
    json_response(['error' => 'invalid_or_expired_token'], 401);
}

json_response([
    'id'    => (int) $user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
]);
