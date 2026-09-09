<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';
$admin = require_admin();

$error = null;
$success = null;
$newClient = null; // set right after creation so we can show the secret once, prominently

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $redirectUri = trim($_POST['redirect_uri'] ?? '');
        $logoutUri = trim($_POST['logout_uri'] ?? '') ?: null;

        if ($name === '' || $redirectUri === '') {
            $error = 'Name and redirect URL are required.';
        } elseif (!filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            $error = 'Redirect URL must be a full, valid URL.';
        } elseif ($logoutUri !== null && !filter_var($logoutUri, FILTER_VALIDATE_URL)) {
            $error = 'Logout URL must be a full, valid URL (or left blank).';
        } else {
            $newClient = create_client($name, $redirectUri, $logoutUri);
        }
    } elseif ($action === 'toggle') {
        $stmt = db()->prepare('UPDATE clients SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $success = 'Updated.';
    } elseif ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM clients WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $success = 'Client app removed.';
    }
}

$clients = db()->query('SELECT * FROM clients ORDER BY created_at DESC')->fetchAll();

admin_page_start('Client apps', 'clients', $admin);
?>

<div class="flex items-center justify-between mb-8">
    <h1 class="font-display text-2xl font-semibold">Client apps</h1>
</div>

<?php render_flash($error, 'error'); ?>
<?php render_flash($success, 'success'); ?>

<?php if ($newClient): ?>
<div class="border border-brass bg-brass/10 rounded p-5 mb-10">
    <p class="text-sm font-semibold text-brass-bright mb-3"><?= e($newClient['name']) ?> is registered. Save these now: the secret won't be highlighted like this again:</p>
    <p class="text-xs text-mute mb-1">Client ID</p>
    <p class="font-mono text-sm mb-3 select-all"><?= e($newClient['client_id']) ?></p>
    <p class="text-xs text-mute mb-1">Client secret</p>
    <p class="font-mono text-sm select-all"><?= e($newClient['client_secret']) ?></p>
</div>
<?php endif; ?>

<div class="border-t border-ink-border">
    <?php foreach ($clients as $client): ?>
    <div class="flex items-center justify-between py-4 border-b border-ink-border gap-4">
        <div class="min-w-0">
            <p class="text-sm font-medium truncate">
                <?= e($client['name']) ?>
                <?php if (!$client['is_active']): ?>
                    <span class="text-rust text-xs ml-2">disabled</span>
                <?php endif; ?>
            </p>
            <p class="text-xs text-mute font-mono truncate"><?= e($client['client_id']) ?> &middot; <?= e($client['redirect_uri']) ?></p>
        </div>
        <div class="flex items-center gap-4 shrink-0">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
                <button class="text-xs text-mute hover:text-paper"><?= $client['is_active'] ? 'Disable' : 'Enable' ?></button>
            </form>
            <form method="POST" onsubmit="return confirm('Remove this client app? Apps using it will stop working immediately.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
                <button class="text-xs text-rust hover:text-rust-bright">Remove</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($clients)): ?>
        <p class="text-mute text-sm py-6">No client apps registered yet, add your first one below.</p>
    <?php endif; ?>
</div>

<div class="mt-12 border-t border-ink-border pt-8">
    <h2 class="font-display text-sm uppercase tracking-wide text-mute mb-4">Register a new client app</h2>
    <form method="POST" class="space-y-6 max-w-md">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">

        <div>
            <label class="block text-xs text-mute mb-1">App name</label>
            <input class="field" type="text" name="name" placeholder="e.g. Invoice Tracker" required>
        </div>
        <div>
            <label class="block text-xs text-mute mb-1">Redirect URL</label>
            <input class="field" type="url" name="redirect_uri" placeholder="https://invoices.yourdomain.com/sso/callback.php" required>
            <p class="text-xs text-mute mt-1">Exactly where this app's callback.php lives, matched precisely with no partial matches.</p>
        </div>
        <div>
            <label class="block text-xs text-mute mb-1">Logout URL <span class="text-mute">(optional)</span></label>
            <input class="field" type="url" name="logout_uri" placeholder="https://invoices.yourdomain.com/sso/logout.php">
        </div>

        <button type="submit" class="bg-brass hover:bg-brass-bright text-ink font-display font-semibold px-6 py-2.5 rounded transition-colors">
            Register app
        </button>
    </form>
</div>

<?php admin_page_end(); ?>
