<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
session_start();

$user = current_sso_user();
if (!$user) {
    redirect('/login.php?next=' . urlencode('/dashboard.php'));
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    csrf_verify();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    if (!password_verify($current, $user['password_hash'])) {
        $error = 'Your current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords don’t match.';
    } else {
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
        $success = 'Password updated.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revoke_access') {
    csrf_verify();
    revoke_consent((int) $user['id'], $_POST['client_id'] ?? '');
    $success = 'Access revoked. That app will ask you to sign in, and to approve access again, next time.';
}

// Every app this user has actually approved (not just ones they've
// recently used). This is what "revoke" removes from.
$connectedApps = list_consented_clients((int) $user['id']);

page_start('Your account');
?>

<div class="max-w-2xl mx-auto px-6 py-16">
    <div class="flex items-center justify-between mb-12">
        <div>
            <p class="text-mute text-xs font-mono mb-1">PHASETIME ACCOUNT</p>
            <h1 class="font-display text-2xl font-semibold"><?= e($user['name']) ?></h1>
            <p class="text-mute text-sm"><?= e($user['email']) ?></p>
        </div>
        <a href="/logout.php" class="text-rust hover:text-rust-bright text-sm">Sign out</a>
    </div>

    <?php render_flash($error, 'error'); ?>
    <?php render_flash($success, 'success'); ?>

    <section class="mb-12">
        <h2 class="font-display text-sm uppercase tracking-wide text-mute mb-4">Connected apps</h2>
        <?php if (empty($connectedApps)): ?>
            <p class="text-mute text-sm">You haven't approved any connected app yet.</p>
        <?php else: ?>
            <div class="border-t border-ink-border">
                <?php foreach ($connectedApps as $app): ?>
                    <?php
                    // json_encode (not just e()) because this string is going into
                    // inline JS, not HTML text. A plain apostrophe in an app name
                    // would otherwise break out of the confirm() string.
                    $confirmMsg = htmlspecialchars(
                        json_encode("Revoke access for {$app['name']}? It will need your approval again next time you sign in there."),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                    <div class="flex items-center justify-between py-3 border-b border-ink-border gap-4">
                        <div class="min-w-0">
                            <p class="text-sm truncate"><?= e($app['name']) ?></p>
                            <p class="text-mute text-xs font-mono">
                                approved <?= e(date('M j, Y', strtotime($app['granted_at']))) ?>
                                <?php if ($app['last_used']): ?>
                                    &middot; last used <?= e(date('M j, Y', strtotime($app['last_used']))) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <form method="POST" class="shrink-0" onsubmit="return confirm(<?= $confirmMsg ?>);">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="revoke_access">
                            <input type="hidden" name="client_id" value="<?= e($app['client_id']) ?>">
                            <button class="text-xs text-rust hover:text-rust-bright">Revoke</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-mute text-xs mt-3">
                Revoking stops future sign-ins there and cuts off any refresh of its access, but if you're
                still actively using that app in another tab right now, it may not notice until it next needs to check in.
            </p>
        <?php endif; ?>
    </section>

    <section>
        <h2 class="font-display text-sm uppercase tracking-wide text-mute mb-4">Change password</h2>
        <form method="POST" class="space-y-6 max-w-sm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div>
                <label class="block text-xs text-mute mb-1">Current password</label>
                <input class="field" type="password" name="current_password" required>
            </div>
            <div>
                <label class="block text-xs text-mute mb-1">New password</label>
                <input class="field" type="password" name="new_password" required minlength="8">
            </div>
            <div>
                <label class="block text-xs text-mute mb-1">Confirm new password</label>
                <input class="field" type="password" name="new_password_confirm" required minlength="8">
            </div>

            <button type="submit" class="bg-brass hover:bg-brass-bright text-ink font-display font-semibold px-6 py-2.5 rounded transition-colors">
                Update password
            </button>
        </form>
    </section>
</div>

<?php page_end(); ?>
