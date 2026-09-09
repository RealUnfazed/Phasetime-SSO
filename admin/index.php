<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';
$admin = require_admin();

$stats = [
    'users'   => (int) db()->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
    'clients' => (int) db()->query('SELECT COUNT(*) c FROM clients WHERE is_active = 1')->fetch()['c'],
    'tokens_today' => (int) db()->query(
        "SELECT COUNT(*) c FROM access_tokens WHERE created_at >= CURDATE()"
    )->fetch()['c'],
    'active_sessions' => (int) db()->query(
        'SELECT COUNT(*) c FROM sso_sessions WHERE expires_at > NOW()'
    )->fetch()['c'],
];

admin_page_start('Dashboard', 'dashboard', $admin);
?>

<h1 class="font-display text-2xl font-semibold mb-8">Dashboard</h1>

<div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-12">
    <?php
    $cards = [
        'Registered users'  => $stats['users'],
        'Connected apps'    => $stats['clients'],
        'Sign-ins today'    => $stats['tokens_today'],
        'Active sessions'   => $stats['active_sessions'],
    ];
    foreach ($cards as $label => $value):
    ?>
    <div>
        <p class="font-display text-3xl font-semibold text-brass-bright"><?= number_format($value) ?></p>
        <p class="text-mute text-xs mt-1"><?= e($label) ?></p>
    </div>
    <?php endforeach; ?>
</div>

<div class="border-t border-ink-border pt-8">
    <h2 class="font-display text-sm uppercase tracking-wide text-mute mb-4">Get a new mini-project connected</h2>
    <ol class="text-sm text-mute space-y-2 list-decimal list-inside">
        <li>Go to <a href="/admin/clients.php" class="text-brass hover:text-brass-bright">Client apps</a> and register the project, and you'll get a client_id and client_secret.</li>
        <li>In that project, drop in <span class="font-mono text-paper">sdk/SSOClient.php</span> and point it at this server with those credentials.</li>
        <li>Call <span class="font-mono text-paper">$sso->requireLogin()</span> on any page that needs a logged-in user.</li>
    </ol>
</div>

<?php admin_page_end(); ?>
