<?php
/**
 * This whole file is the "before you have SSO" vs "after" story in one
 * page: three lines of setup, one call to see who's logged in.
 */
require __DIR__ . '/../../sdk/SSOClient.php';

$sso = new SSOClient(require __DIR__ . '/sso_config.php');
$user = $sso->user(); // does NOT force a redirect, just checks
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Demo Client</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:sans-serif}</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center">
<div class="text-center max-w-sm px-6">
    <p class="text-xs text-slate-500 mb-2 font-mono">DEMO CLIENT PROJECT</p>
    <?php if ($user): ?>
        <h1 class="text-xl font-semibold mb-4">Welcome back, <?= htmlspecialchars($user['name']) ?></h1>
        <a href="/dashboard.php" class="text-amber-400 underline">Go to your dashboard</a>
        <p class="mt-6"><a href="/logout.php" class="text-red-400 text-sm">Log out</a></p>
    <?php else: ?>
        <h1 class="text-xl font-semibold mb-4">You're not signed in</h1>
        <p class="text-slate-400 text-sm mb-6">
            This mini-project has no login form of its own. It delegates entirely to the SSO server.
        </p>
        <a href="/dashboard.php" class="bg-amber-500 text-slate-950 font-semibold px-6 py-2.5 rounded inline-block">
            Sign in with Phasetime
        </a>
    <?php endif; ?>
</div>
</body>
</html>
