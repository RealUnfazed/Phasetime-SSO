<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
session_start();

function require_admin(): array
{
    $user = current_sso_user();
    if (!$user) {
        redirect('/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    }
    if (!$user['is_admin']) {
        http_response_code(403);
        die('You do not have access to the admin panel.');
    }
    return $user;
}
