<?php
require __DIR__ . '/../../sdk/SSOClient.php';

$sso = new SSOClient(require __DIR__ . '/sso_config.php');
$user = $sso->requireLogin(); // <- this one line is the entire login wall
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Demo Client Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center">
<div class="text-center">
    <p class="text-xs text-slate-500 mb-2 font-mono">PROTECTED PAGE</p>
    <h1 class="text-xl font-semibold mb-2">Hello, <?= htmlspecialchars($user['name']) ?></h1>
    <p class="text-slate-400 text-sm mb-6"><?= htmlspecialchars($user['email']) ?></p>
    <a href="/index.php" class="text-amber-400 underline text-sm">Home</a>
    &nbsp;·&nbsp;
    <a href="/logout.php" class="text-red-400 text-sm">Log out</a>
</div>
</body>
</html>
