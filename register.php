<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/captcha.php';
session_start();

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
$error = null;
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $captchaOk = captcha_verify($_POST['captcha_id'] ?? '', $_POST['captcha_answer'] ?? '');
    $captchaOk = $captchaOk && honeypot_passed();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (!$captchaOk) {
        $error = 'That didn’t look right, please try the puzzle again.';
    } elseif ($name === '' || $email === '' || $password === '') {
        $error = 'Fill in every field.';
    } elseif (!validate_email($email)) {
        $error = 'That email address doesn’t look right.';
    } elseif (strlen($password) < 8) {
        $error = 'Use at least 8 characters for your password.';
    } elseif ($password !== $confirm) {
        $error = 'Those passwords don’t match.';
    } elseif (find_user_by_email($email)) {
        $error = 'An account with that email already exists.';
    } else {
        $user = create_user($name, $email, $password);
        start_sso_session((int) $user['id']);
        redirect($next);
    }
}

$captcha = captcha_new();

page_start('Create account');
?>

<div class="flex min-h-screen">
    <div class="hidden md:flex md:w-2/5 bg-ink-panel border-r border-ink-border flex-col justify-between p-12">
        <div class="flex items-center gap-2">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="8" cy="8" r="5.5" stroke="#C89B3C" stroke-width="1.6"/>
                <rect x="7.2" y="12.5" width="1.6" height="9" fill="#C89B3C"/>
                <rect x="8.8" y="16" width="4" height="1.6" fill="#C89B3C"/>
                <rect x="8.8" y="19" width="3" height="1.6" fill="#C89B3C"/>
            </svg>
            <span class="font-display font-semibold text-lg tracking-tight">Phasetime</span>
        </div>
        <div>
            <p class="font-display text-3xl leading-snug text-paper">Register once.<br>Skip it<br>everywhere else.</p>
            <p class="text-mute text-sm mt-4 max-w-xs">This account will work across every connected project, today and any new one you add later.</p>
        </div>
        <p class="text-mute text-xs font-mono"><?= e(display_host(config()['base_url'])) ?></p>
    </div>

    <div class="flex-1 flex items-center justify-center p-8">
        <div class="w-full max-w-sm">
            <h1 class="font-display text-2xl font-semibold mb-1">Create your account</h1>
            <p class="text-mute text-sm mb-8">Takes a minute. You won't do this again.</p>

            <?php render_flash($error, 'error'); ?>

            <form method="POST" class="space-y-6">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">

                <div>
                    <label class="block text-xs text-mute mb-1" for="name">Name</label>
                    <input class="field" type="text" id="name" name="name" value="<?= e($name) ?>" required autofocus>
                </div>

                <div>
                    <label class="block text-xs text-mute mb-1" for="email">Email</label>
                    <input class="field" type="email" id="email" name="email" value="<?= e($email) ?>" required>
                </div>

                <div>
                    <label class="block text-xs text-mute mb-1" for="password">Password</label>
                    <input class="field" type="password" id="password" name="password" required minlength="8">
                </div>

                <div>
                    <label class="block text-xs text-mute mb-1" for="password_confirm">Confirm password</label>
                    <input class="field" type="password" id="password_confirm" name="password_confirm" required minlength="8">
                </div>

                <?php captcha_widget_html($captcha); ?>

                <button type="submit" class="w-full bg-brass hover:bg-brass-bright text-ink font-display font-semibold py-3 rounded transition-colors">
                    Create account
                </button>
            </form>

            <p class="text-mute text-sm mt-8">
                Already registered?
                <a class="text-brass hover:text-brass-bright" href="/login.php?next=<?= urlencode($next) ?>">Sign in</a>
            </p>
        </div>
    </div>
</div>

<?php page_end(); ?>
