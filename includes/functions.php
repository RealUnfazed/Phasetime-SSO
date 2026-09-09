<?php
/**
 * Small stateless helpers used across the SSO server.
 */

function config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }
    return $config;
}

/** Cryptographically random URL-safe token. */
function random_token(int $bytes = 32): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Escape for safe HTML output. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// -----------------------------------------------------------------
// CSRF protection: one token per browser session, checked on every
// state-changing POST (login, register, admin forms, etc).
// -----------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = random_token(32);
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(419);
        die('Your session expired, please go back and try again.');
    }
}

/**
 * Only ever redirect to a path on THIS server after login/register.
 * Rejects absolute URLs, protocol-relative "//evil.com" URLs, and
 * anything not starting with a single "/". This is what stops the
 * classic ?next= open-redirect vulnerability.
 */
function safe_next(?string $next, string $default = '/dashboard.php'): string
{
    if (!$next || $next[0] !== '/' || str_starts_with($next, '//')) {
        return $default;
    }
    return $next;
}

function validate_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** Strips the scheme off base_url for display, e.g. "https://sso.example.com" -> "sso.example.com". */
function display_host(string $url): string
{
    return preg_replace('#^https?://#', '', rtrim($url, '/'));
}

/**
 * Very small, dependency-free redirect_uri validator: exact match
 * against what the client registered. Do NOT loosen this to a
 * prefix/substring match. That's the classic open-redirect hole
 * in homemade OAuth implementations.
 */
function redirect_uri_is_valid(string $registered, string $provided): bool
{
    return hash_equals($registered, $provided);
}
