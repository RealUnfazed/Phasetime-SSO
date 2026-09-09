<?php
require __DIR__ . '/../../../sdk/SSOClient.php';

$sso = new SSOClient(require __DIR__ . '/../sso_config.php');
$sso->handleCallback(); // exchanges the code, stores the session, redirects onward
