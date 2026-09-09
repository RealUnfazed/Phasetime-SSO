<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/captcha.php';
session_start();

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Evaluated separately (not short-circuited) so the challenge is
    // always burned exactly once, whichever check actually fails.
    $captchaOk = captcha_verify($_POST['captcha_id'] ?? '', $_POST['captcha_answer'] ?? '');
    $captchaOk = $captchaOk && honeypot_passed();

    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!$captchaOk) {
        $error = 'That didn’t look right, please try the puzzle again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $result = attempt_login($email, $password);
        if (is_array($result)) {
            start_sso_session((int) $result['id']);
            redirect($next);
        }
        $error = match ($result) {
            'not_found', 'bad_password' => 'That email or password isn’t right.',
            'locked'   => 'Too many attempts. This account is temporarily locked, try again shortly.',
            'inactive' => 'This account has been deactivated.',
            default    => 'Something went wrong. Please try again.',
        };
    }
}

// Already logged in? Skip the form entirely. This is the "single" in single sign-on.
if (!$error && current_sso_user()) {
    redirect($next);
}

$captcha = captcha_new();

page_start('Sign in');
?>

<div class="flex min-h-screen">
    <!-- Brand panel -->
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
            <p class="font-display text-3xl leading-snug text-paper">One account.<br>Every project<br>you've built.</p>
            <p class="text-mute text-sm mt-4 max-w-xs">Sign in here once, and every connected app trusts it. No separate password to remember or reset.</p>
        </div>
        <p class="text-mute text-xs font-mono"><?= e(display_host(config()['base_url'])) ?></p>
    </div>

    <!-- Form panel -->
    <div class="flex-1 flex items-center justify-center p-8">
        <div class="w-full max-w-sm">
            <h1 class="font-display text-2xl font-semibold mb-1">Sign in</h1>
            <p class="text-mute text-sm mb-8">Use your Phasetime account.</p>

            <?php render_flash($error, 'error'); ?>

            <form method="POST" class="space-y-6">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">

                <div>
                    <label class="block text-xs text-mute mb-1" for="email">Email</label>
                    <input class="field" type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
                </div>

                <div>
                    <label class="block text-xs text-mute mb-1" for="password">Password</label>
                    <input class="field" type="password" id="password" name="password" required>
                </div>

                <?php captcha_widget_html($captcha); ?>

                <button type="submit" class="w-full bg-brass hover:bg-brass-bright text-ink font-display font-semibold py-3 rounded transition-colors">
                    Sign in
                </button>
            </form>

            <p class="text-mute text-sm mt-8">
                New here?
                <a class="text-brass hover:text-brass-bright" href="/register.php?next=<?= urlencode($next) ?>">Create an account</a>
            </p>
        </div>
    </div>
</div>

<?php page_end(); ?>
