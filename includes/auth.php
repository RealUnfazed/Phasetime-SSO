<?php
/**
 * Core SSO authentication logic.
 *
 * Everything here is deliberately framework-free so you can read it
 * top to bottom and know exactly what your auth server does.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ===================================================================
//  USERS
// ===================================================================

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

function find_user_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function create_user(string $name, string $email, string $password): array
{
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = db()->prepare(
        'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)'
    );
    $stmt->execute([$name, $email, $hash]);
    return find_user_by_id((int) db()->lastInsertId());
}

/**
 * Verify credentials with basic brute-force lockout.
 * Returns the user row on success, or a string error code on failure:
 * 'not_found' | 'locked' | 'bad_password' | 'inactive'
 */
function attempt_login(string $email, string $password): array|string
{
    $user = find_user_by_email($email);
    if (!$user) {
        return 'not_found';
    }
    if (!$user['is_active']) {
        return 'inactive';
    }
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return 'locked';
    }

    if (!password_verify($password, $user['password_hash'])) {
        $cfg = config()['security'];
        $failed = $user['failed_logins'] + 1;
        $lockedUntil = null;
        if ($failed >= $cfg['max_failed_logins']) {
            $lockedUntil = date('Y-m-d H:i:s', time() + $cfg['lockout_minutes'] * 60);
            $failed = 0;
        }
        $stmt = db()->prepare('UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?');
        $stmt->execute([$failed, $lockedUntil, $user['id']]);
        return $lockedUntil ? 'locked' : 'bad_password';
    }

    // Success, reset the failure counter.
    $stmt = db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?');
    $stmt->execute([$user['id']]);

    return $user;
}

// ===================================================================
//  CENTRAL SSO SESSION
//  This is the cookie set on the SSO server's own domain. As long as
//  it's alive, /authorize will silently re-authenticate the user into
//  any client app without showing the login form again.
// ===================================================================

function start_sso_session(int $userId): void
{
    $cfg = config()['session'];
    $sessionId = random_token(32);
    $expiresAt = date('Y-m-d H:i:s', time() + $cfg['lifetime_secs']);

    $stmt = db()->prepare(
        'INSERT INTO sso_sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $sessionId,
        $userId,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        $expiresAt,
    ]);

    set_sso_cookie($sessionId, time() + $cfg['lifetime_secs']);
}

/**
 * Sends the SSO session cookie with an absolute expiry time. Because it has
 * an expiry (not a session cookie), it survives the browser being closed.
 */
function set_sso_cookie(string $value, int $expires): void
{
    $cfg = config()['session'];
    setcookie($cfg['cookie_name'], $value, [
        'expires'  => $expires,
        'path'     => '/',
        'domain'   => $cfg['cookie_domain'] ?? '',
        'secure'   => $cfg['cookie_secure'],
        'httponly' => true,
        'samesite' => $cfg['cookie_samesite'],
    ]);
}

/**
 * Sliding expiry: push a session's expiry (database row AND cookie) out to a
 * full lifetime from now, but only if it hasn't been extended recently. The
 * throttle (renew_after_secs, default one day) keeps this from turning every
 * page view into a database write.
 *
 * Skipped if output has already started, because the new cookie couldn't be
 * sent and the row and cookie must always move together. The next request
 * will renew it instead.
 */
function renew_sso_session_if_due(array $session): void
{
    $cfg        = config()['session'];
    $lifetime   = (int) $cfg['lifetime_secs'];
    // Never wait longer than half the lifetime, so short custom lifetimes still slide.
    $renewAfter = min((int) ($cfg['renew_after_secs'] ?? 86400), intdiv($lifetime, 2));

    $remaining = strtotime($session['expires_at']) - time();
    if ($lifetime - $remaining < $renewAfter || headers_sent()) {
        return;
    }

    $newExpiry = time() + $lifetime;
    db()->prepare('UPDATE sso_sessions SET expires_at = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s', $newExpiry), $session['id']]);
    set_sso_cookie($session['id'], $newExpiry);
}

/** Returns the logged-in user's row, or null if there's no valid SSO session. */
function current_sso_user(): ?array
{
    $cfg = config()['session'];
    $sessionId = $_COOKIE[$cfg['cookie_name']] ?? null;
    if (!$sessionId) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM sso_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session || strtotime($session['expires_at']) < time()) {
        return null;
    }

    $user = find_user_by_id((int) $session['user_id']);

    // A deactivated account must not keep a working session. Sessions that
    // last months can't rely on "it'll expire soon anyway" any more.
    if (!$user || !$user['is_active']) {
        return null;
    }

    renew_sso_session_if_due($session);

    return $user;
}

function destroy_sso_session(): void
{
    $cfg = config()['session'];
    $sessionId = $_COOKIE[$cfg['cookie_name']] ?? null;

    if ($sessionId) {
        $stmt = db()->prepare('DELETE FROM sso_sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
    }

    set_sso_cookie('', time() - 3600);
}

// ===================================================================
//  CLIENT APPS (the mini-projects)
// ===================================================================

function find_client(string $clientId): ?array
{
    $stmt = db()->prepare('SELECT * FROM clients WHERE client_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$clientId]);
    return $stmt->fetch() ?: null;
}

function create_client(string $name, string $redirectUri, ?string $logoutUri = null): array
{
    $clientId     = 'cid_' . random_token(12);
    $clientSecret = random_token(32);

    $stmt = db()->prepare(
        'INSERT INTO clients (client_id, client_secret, name, redirect_uri, logout_uri) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$clientId, $clientSecret, $name, $redirectUri, $logoutUri]);

    return find_client($clientId);
}

// ===================================================================
//  CONSENT
//  Whether a user has actually agreed to let a given client app see
//  their profile. Separate from tokens on purpose: tokens expire in
//  an hour, but a user's "yes, I trust this app" decision should
//  persist until they deliberately revoke it from their dashboard,
//  otherwise they'd get a consent screen every single hour.
// ===================================================================

function has_consent(int $userId, string $clientId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM user_consents WHERE user_id = ? AND client_id = ? LIMIT 1');
    $stmt->execute([$userId, $clientId]);
    return (bool) $stmt->fetch();
}

function grant_consent(int $userId, string $clientId): void
{
    $stmt = db()->prepare(
        'INSERT INTO user_consents (user_id, client_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([$userId, $clientId]);
}

/**
 * Revoking means: forget the consent decision (so next login shows the
 * screen again) AND kill any tokens that app is currently holding, so
 * it can't silently refresh its way to a new one. It does NOT reach
 * into that app and end a session it already established locally.
 * No SSO can do that without the client also checking back in, which
 * is why /userinfo and token refresh are where this actually bites.
 */
function revoke_consent(int $userId, string $clientId): void
{
    db()->prepare('DELETE FROM user_consents WHERE user_id = ? AND client_id = ?')
        ->execute([$userId, $clientId]);

    db()->prepare('UPDATE access_tokens SET revoked = 1 WHERE user_id = ? AND client_id = ?')
        ->execute([$userId, $clientId]);
}

/** Every client app a user has ever approved, most recently granted first. */
function list_consented_clients(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT c.name, c.client_id, uc.granted_at,
                (SELECT MAX(t.created_at) FROM access_tokens t
                  WHERE t.user_id = uc.user_id AND t.client_id = uc.client_id) AS last_used
         FROM user_consents uc
         JOIN clients c ON c.client_id = uc.client_id
         WHERE uc.user_id = ?
         ORDER BY uc.granted_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ===================================================================
//  AUTHORIZATION CODE  (step: /authorize -> redirect back to client)
// ===================================================================

function issue_auth_code(int $userId, string $clientId, string $redirectUri): string
{
    $code = random_token(32);
    $ttl  = config()['tokens']['auth_code_ttl_secs'];

    $stmt = db()->prepare(
        'INSERT INTO auth_codes (code, user_id, client_id, redirect_uri, expires_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$code, $userId, $clientId, $redirectUri, date('Y-m-d H:i:s', time() + $ttl)]);

    return $code;
}

/**
 * Redeem an auth code for a user id. Returns the user id on success,
 * or a string error code: 'invalid' | 'expired' | 'already_used' | 'redirect_mismatch'
 */
function redeem_auth_code(string $code, string $clientId, string $redirectUri): int|string
{
    $stmt = db()->prepare('SELECT * FROM auth_codes WHERE code = ? AND client_id = ? LIMIT 1');
    $stmt->execute([$code, $clientId]);
    $row = $stmt->fetch();

    if (!$row) {
        return 'invalid';
    }
    if ($row['used']) {
        return 'already_used';
    }
    if (strtotime($row['expires_at']) < time()) {
        return 'expired';
    }
    if (!redirect_uri_is_valid($row['redirect_uri'], $redirectUri)) {
        return 'redirect_mismatch';
    }

    // Single use: mark it burned immediately, regardless of what happens next.
    $upd = db()->prepare('UPDATE auth_codes SET used = 1 WHERE id = ?');
    $upd->execute([$row['id']]);

    return (int) $row['user_id'];
}

// ===================================================================
//  ACCESS / REFRESH TOKENS  (step: /token -> issued to client backend)
// ===================================================================

function issue_tokens(int $userId, string $clientId): array
{
    $cfg = config()['tokens'];
    $accessToken  = random_token(32);
    $refreshToken = random_token(32);

    $stmt = db()->prepare(
        'INSERT INTO access_tokens (access_token, refresh_token, user_id, client_id, expires_at, refresh_expires_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $accessToken,
        $refreshToken,
        $userId,
        $clientId,
        date('Y-m-d H:i:s', time() + $cfg['access_token_ttl_secs']),
        date('Y-m-d H:i:s', time() + $cfg['refresh_token_ttl_secs']),
    ]);

    return [
        'access_token'  => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type'    => 'Bearer',
        'expires_in'    => $cfg['access_token_ttl_secs'],
    ];
}

/** Returns the user row for a valid, unexpired, unrevoked access token, or null. */
function user_for_access_token(string $token): ?array
{
    $stmt = db()->prepare('SELECT * FROM access_tokens WHERE access_token = ? AND revoked = 0 LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row || strtotime($row['expires_at']) < time()) {
        return null;
    }

    return find_user_by_id((int) $row['user_id']);
}

function refresh_access_token(string $refreshToken, string $clientId): array|string
{
    $stmt = db()->prepare(
        'SELECT * FROM access_tokens WHERE refresh_token = ? AND client_id = ? AND revoked = 0 LIMIT 1'
    );
    $stmt->execute([$refreshToken, $clientId]);
    $row = $stmt->fetch();

    if (!$row) {
        return 'invalid';
    }
    if (!$row['refresh_expires_at'] || strtotime($row['refresh_expires_at']) < time()) {
        return 'expired';
    }

    // Revoke the old token pair, issue a fresh one (rotation).
    $upd = db()->prepare('UPDATE access_tokens SET revoked = 1 WHERE id = ?');
    $upd->execute([$row['id']]);

    return issue_tokens((int) $row['user_id'], $clientId);
}
