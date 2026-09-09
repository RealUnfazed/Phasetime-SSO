<?php
require __DIR__ . '/../../sdk/SSOClient.php';

$sso = new SSOClient(require __DIR__ . '/sso_config.php');

// Local-only logout: just ends this app's own session. Anything else
// you're signed into via Phasetime stays signed in. This is the
// normal choice for a per-app "Log out" link.
$sso->logout('/index.php');

// If you specifically wanted a "sign out of everything" button
// instead (ends the central Phasetime session too, so every other
// connected app you're using will ask for a password again next
// time), it would be:
//
//   $sso->logoutEverywhere();
