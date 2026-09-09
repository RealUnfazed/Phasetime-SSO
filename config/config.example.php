<?php
/**
 * Central configuration TEMPLATE.
 *
 * This file is safe to commit. It has no real secrets, only
 * placeholders and sane fallbacks. Your actual working config lives
 * in config/config.php, which is gitignored on purpose: copy this
 * file there and fill in your real values.
 *
 *   cp config/config.example.php config/config.php
 *
 * In production, prefer setting real values via environment variables
 * over editing the fallback strings directly. The getenv() calls
 * below make that easy. If your host doesn't support environment
 * variables, editing the fallbacks is fine too; just make sure that
 * edited file is config.php, never this one, and never gets committed.
 */

return [

    // -----------------------------------------------------------
    // Database
    // -----------------------------------------------------------
    'db' => [
        'host'    => getenv('SSO_DB_HOST') ?: '127.0.0.1',
        'name'    => getenv('SSO_DB_NAME') ?: 'phasetime_db',
        'user'    => getenv('SSO_DB_USER') ?: 'root',
        'pass'    => getenv('SSO_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    // -----------------------------------------------------------
    // This SSO server's own identity
    // -----------------------------------------------------------
    // Full base URL of THIS install, no trailing slash.
    // Every client's redirect_uri and every SDK config points back here.
    'base_url' => getenv('SSO_BASE_URL') ?: 'https://sso.example.com',

    // -----------------------------------------------------------
    // Central SSO session (the "are you logged in at all" cookie)
    // -----------------------------------------------------------
    'session' => [
        'cookie_name'     => 'sso_session',
        'lifetime_secs'   => 60 * 60 * 8,      // 8 hours of inactivity-free life
        // Only set a custom cookie domain if every client shares a parent
        // domain (e.g. ".yourdomain.com"). Leave null if your mini-projects
        // live on unrelated domains. The redirect flow doesn't need it.
        'cookie_domain'   => getenv('SSO_COOKIE_DOMAIN') ?: null,
        'cookie_secure'   => true,   // requires HTTPS, turn off only for local http dev
        'cookie_samesite' => 'Lax',
    ],

    // -----------------------------------------------------------
    // Token lifetimes
    // -----------------------------------------------------------
    'tokens' => [
        'auth_code_ttl_secs'    => 60,             // auth codes are single-use, keep this short
        'access_token_ttl_secs' => 60 * 60,        // 1 hour
        'refresh_token_ttl_secs'=> 60 * 60 * 24 * 30, // 30 days
    ],

    // -----------------------------------------------------------
    // Login throttling
    // -----------------------------------------------------------
    'security' => [
        'max_failed_logins' => 5,
        'lockout_minutes'   => 15,
    ],
];
