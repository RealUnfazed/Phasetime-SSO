<?php
/**
 * ONE-TIME SETUP. Creates the first admin account through the browser,
 * for hosts where you can't run `php bin/create_admin.php` from a shell.
 *
 * Visit this page once, submit the form, then DELETE THIS FILE.
 * It refuses to run a second time once any admin account exists, but
 * leaving an installer sitting on a live server is still bad practice,
 * so delete it as soon as you're done.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
session_start();

$adminCount = (int) db()->query('SELECT COUNT(*) c FROM users WHERE is_admin = 1')->fetch()['c'];

if ($adminCount > 0) {
    http_response_code(403);
    page_start('Setup already complete');
    ?>
    <div class="flex min-h-screen items-center justify-center p-8">
        <div class="max-w-md text-center">
            <h1 class="font-display text-xl font-semibold mb-2">Setup already ran</h1>
            <p class="text-mute text-sm mb-4">
                An admin account already exists, so this installer won't create another one.
            </p>
            <p class="text-rust text-sm">
                Delete <span class="font-mono">setup.php</span> from your server now. It should not stay online.
            </p>
        </div>
    </div>
    <?php
    page_end();
    exit;
}

$error = null;
$done = false;

// Deliberately NOT pre-filled with a real name/email/password. A
// convenience default here would be a credential sitting in plain
// text in version control forever. A random placeholder password
// nudges you to actually type your own instead of clicking through.
$name = '';
$email = '';
$password = bin2hex(random_bytes(4)); // just a placeholder, not meant to be used as-is

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Fill in every field.';
    } elseif (!validate_email($email)) {
        $error = 'That email address doesn’t look right.';
    } elseif (strlen($password) < 8) {
        $error = 'Use at least 8 characters for the password.';
    } elseif (find_user_by_email($email)) {
        $error = 'A user with that email already exists. Promote them via bin/create_admin.php or edit the database directly.';
    } else {
        $user = create_user($name, $email, $password);
        db()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$user['id']]);
        $done = true;
    }
}

page_start('Set up admin account');
?>
<div class="flex min-h-screen items-center justify-center p-8">
    <div class="w-full max-w-sm">
        <?php if ($done): ?>
            <h1 class="font-display text-xl font-semibold mb-2 text-teal-bright">Admin account created</h1>
            <p class="text-sm text-paper mb-1"><?= e($name) ?> &middot; <?= e($email) ?></p>
            <p class="text-mute text-sm mb-6">You can sign in at <a class="text-brass hover:text-brass-bright" href="/login.php">/login.php</a> now.</p>
            <div class="border border-rust bg-rust/10 rounded p-4">
                <p class="text-rust-bright text-sm font-semibold mb-1">Last step: delete this file</p>
                <p class="text-mute text-sm">Remove <span class="font-mono">setup.php</span> from your server. Leaving it online is a standing risk even though it refuses to run twice.</p>
            </div>
        <?php else: ?>
            <h1 class="font-display text-xl font-semibold mb-1">Create the admin account</h1>
            <p class="text-mute text-sm mb-8">Runs once. Type your real name, email, and a password you'll actually use. The password field starts with a random placeholder, not a real suggestion.</p>

            <?php render_flash($error, 'error'); ?>

            <form method="POST" class="space-y-6">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-xs text-mute mb-1">Name</label>
                    <input class="field" type="text" name="name" value="<?= e($name) ?>" required>
                </div>
                <div>
                    <label class="block text-xs text-mute mb-1">Email</label>
                    <input class="field" type="email" name="email" value="<?= e($email) ?>" required>
                </div>
                <div>
                    <label class="block text-xs text-mute mb-1">Password</label>
                    <input class="field" type="text" name="password" value="<?= e($password) ?>" required minlength="8">
                    <p class="text-xs text-mute mt-1">Shown in plain text on purpose since this is a one-time setup screen. Change it from the dashboard right after your first login.</p>
                </div>
                <button type="submit" class="w-full bg-brass hover:bg-brass-bright text-ink font-display font-semibold py-3 rounded transition-colors">
                    Create admin account
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php page_end(); ?>
