<?php
require_once __DIR__ . '/includes/auth.php';
session_start();

redirect(current_sso_user() ? '/dashboard.php' : '/login.php');
