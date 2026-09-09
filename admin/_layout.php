<?php
/**
 * Admin panel chrome. $active should be one of: dashboard, clients, users.
 */
require_once __DIR__ . '/../includes/functions.php';

function admin_page_start(string $title, string $active, array $admin): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> · Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="/assets/js/tw-config.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body class="bg-ink text-paper font-body min-h-screen flex">

<aside class="w-56 bg-ink-panel border-r border-ink-border flex flex-col shrink-0">
    <div class="flex items-center gap-2 px-6 py-6">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="5.5" stroke="#C89B3C" stroke-width="1.6"/>
            <rect x="7.2" y="12.5" width="1.6" height="9" fill="#C89B3C"/>
            <rect x="8.8" y="16" width="4" height="1.6" fill="#C89B3C"/>
            <rect x="8.8" y="19" width="3" height="1.6" fill="#C89B3C"/>
        </svg>
        <span class="font-display font-semibold tracking-tight">Phasetime</span>
    </div>

    <nav class="flex-1 px-3 space-y-1">
        <?php
        $links = [
            'dashboard' => ['/admin/index.php', 'Dashboard'],
            'clients'   => ['/admin/clients.php', 'Client apps'],
            'users'     => ['/admin/users.php', 'Users'],
        ];
        foreach ($links as $key => [$href, $label]):
            $isActive = $key === $active;
        ?>
        <a href="<?= e($href) ?>"
           class="block px-3 py-2 rounded text-sm <?= $isActive ? 'bg-ink-soft text-brass-bright' : 'text-mute hover:text-paper hover:bg-ink-soft' ?>">
            <?= e($label) ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="px-6 py-5 border-t border-ink-border">
        <p class="text-xs text-mute truncate"><?= e($admin['email']) ?></p>
        <a href="/logout.php" class="text-xs text-rust hover:text-rust-bright">Sign out</a>
    </div>
</aside>

<main class="flex-1 px-10 py-10 max-w-4xl">
<?php
}

function admin_page_end(): void
{
    ?>
</main>
</body>
</html>
<?php
}
