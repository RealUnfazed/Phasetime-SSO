<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_layout.php';
$admin = require_admin();

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($id === (int) $admin['id'] && in_array($action, ['toggle_active', 'toggle_admin'], true)) {
        $error = 'You can’t change your own access from here.';
    } elseif ($action === 'toggle_active') {
        db()->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
        $success = 'Updated.';
    } elseif ($action === 'toggle_admin') {
        db()->prepare('UPDATE users SET is_admin = NOT is_admin WHERE id = ?')->execute([$id]);
        $success = 'Updated.';
    } elseif ($action === 'unlock') {
        db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([$id]);
        $success = 'Account unlocked.';
    }
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = db()->prepare('SELECT * FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT 100');
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = db()->query('SELECT * FROM users ORDER BY created_at DESC LIMIT 100');
}
$users = $stmt->fetchAll();

admin_page_start('Users', 'users', $admin);
?>

<div class="flex items-center justify-between mb-8 gap-4">
    <h1 class="font-display text-2xl font-semibold">Users</h1>
    <form method="GET" class="flex-1 max-w-xs">
        <input class="field" type="search" name="q" placeholder="Search by name or email" value="<?= e($search) ?>">
    </form>
</div>

<?php render_flash($error, 'error'); ?>
<?php render_flash($success, 'success'); ?>

<div class="border-t border-ink-border">
    <?php foreach ($users as $u): ?>
    <div class="flex items-center justify-between py-4 border-b border-ink-border gap-4">
        <div class="min-w-0">
            <p class="text-sm font-medium truncate">
                <?= e($u['name']) ?>
                <?php if ($u['is_admin']): ?><span class="text-brass text-xs ml-2">admin</span><?php endif; ?>
                <?php if (!$u['is_active']): ?><span class="text-rust text-xs ml-2">deactivated</span><?php endif; ?>
                <?php if ($u['locked_until'] && strtotime($u['locked_until']) > time()): ?><span class="text-rust text-xs ml-2">locked</span><?php endif; ?>
            </p>
            <p class="text-xs text-mute truncate"><?= e($u['email']) ?></p>
        </div>
        <div class="flex items-center gap-4 shrink-0">
            <?php if ($u['locked_until'] && strtotime($u['locked_until']) > time()): ?>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="unlock">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button class="text-xs text-mute hover:text-paper">Unlock</button>
            </form>
            <?php endif; ?>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_admin">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button class="text-xs text-mute hover:text-paper" <?= (int) $u['id'] === (int) $admin['id'] ? 'disabled' : '' ?>>
                    <?= $u['is_admin'] ? 'Remove admin' : 'Make admin' ?>
                </button>
            </form>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button class="text-xs <?= $u['is_active'] ? 'text-rust hover:text-rust-bright' : 'text-teal hover:text-teal-bright' ?>" <?= (int) $u['id'] === (int) $admin['id'] ? 'disabled' : '' ?>>
                    <?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
        <p class="text-mute text-sm py-6">No users found.</p>
    <?php endif; ?>
</div>

<?php admin_page_end(); ?>
