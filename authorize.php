<?php
/**
 * GET/POST /authorize.php?client_id=...&redirect_uri=...&state=...
 *
 * A mini-project sends the user's browser here. What happens next:
 *
 *  - Not logged in at all -> off to /login.php, then straight back here.
 *  - Logged in, but this is the FIRST time this client has asked ->
 *    show a consent screen ("X wants to sign you in, this shares your
 *    name and email, Allow or Deny"). The user's decision is stored.
 *  - Logged in AND consent was already granted before -> skip the
 *    screen entirely and bounce straight back to the client with a
 *    fresh code. That silent bounce is what makes it "single" sign-on
 *    instead of "type your password again for every app" sign-on.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
session_start();

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$source = $isPost ? $_POST : $_GET;

$clientId    = $source['client_id'] ?? '';
$redirectUri = $source['redirect_uri'] ?? '';
$state       = $source['state'] ?? '';

$client = $clientId ? find_client($clientId) : null;

// Validate BEFORE ever redirecting anywhere. An unverified redirect_uri
// must never be used, or this becomes an open-redirect gadget. Checked
// on both GET and POST since the POST body is still user-suppliable.
if (!$client || !redirect_uri_is_valid($client['redirect_uri'], $redirectUri)) {
    http_response_code(400);
    page_start('Invalid request');
    ?>
    <div class="flex min-h-screen items-center justify-center p-8">
        <div class="max-w-md text-center">
            <h1 class="font-display text-xl font-semibold mb-2">This app isn't set up correctly</h1>
            <p class="text-mute text-sm">
                The application that sent you here isn't registered, or its redirect address doesn't match
                what's on file. If you're the developer, check the client's <span class="font-mono text-brass">client_id</span>
                and <span class="font-mono text-brass">redirect_uri</span> in the admin panel.
            </p>
        </div>
    </div>
    <?php
    page_end();
    exit;
}

$user = current_sso_user();

if (!$user) {
    // Only reachable via GET in normal use (reaching the consent form at
    // all already implies you were logged in) but handled either way.
    $backHere = '/authorize.php?' . http_build_query([
        'client_id'    => $clientId,
        'redirect_uri' => $redirectUri,
        'state'        => $state,
    ]);
    redirect('/login.php?next=' . urlencode($backHere));
}

$separator = str_contains($redirectUri, '?') ? '&' : '?';

if ($isPost) {
    csrf_verify();
    $decision = $_POST['decision'] ?? '';

    if ($decision === 'allow') {
        grant_consent((int) $user['id'], $clientId);
        $code = issue_auth_code((int) $user['id'], $clientId, $redirectUri);
        redirect($redirectUri . $separator . 'code=' . urlencode($code) . '&state=' . urlencode($state));
    }

    // Denied: send a standard OAuth-style error back instead of a code.
    // The client's callback.php should check for ?error= before assuming
    // a ?code= is coming.
    redirect($redirectUri . $separator . 'error=access_denied&state=' . urlencode($state));
}

// From here on it's a GET with a logged-in user.
if (has_consent((int) $user['id'], $clientId)) {
    $code = issue_auth_code((int) $user['id'], $clientId, $redirectUri);
    redirect($redirectUri . $separator . 'code=' . urlencode($code) . '&state=' . urlencode($state));
}

// First time this user has seen this client, so ask before sharing anything.
page_start('Authorize ' . $client['name']);
?>
<div class="flex min-h-screen items-center justify-center p-8">
    <div class="w-full max-w-sm">
        <div class="flex items-center gap-2 mb-10">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="8" cy="8" r="5.5" stroke="#C89B3C" stroke-width="1.6"/>
                <rect x="7.2" y="12.5" width="1.6" height="9" fill="#C89B3C"/>
                <rect x="8.8" y="16" width="4" height="1.6" fill="#C89B3C"/>
                <rect x="8.8" y="19" width="3" height="1.6" fill="#C89B3C"/>
            </svg>
            <span class="font-display font-semibold tracking-tight">Phasetime</span>
        </div>

        <h1 class="font-display text-xl font-semibold mb-1"><?= e($client['name']) ?> wants to sign you in</h1>
        <p class="text-mute text-sm mb-6">Signed in as <span class="text-paper"><?= e($user['email']) ?></span></p>

        <div class="border border-ink-border rounded p-4 mb-8">
            <p class="text-xs text-mute mb-3">This will let <?= e($client['name']) ?> see:</p>
            <ul class="text-sm space-y-1.5">
                <li>&middot; Your name: <span class="text-paper"><?= e($user['name']) ?></span></li>
                <li>&middot; Your email: <span class="text-paper"><?= e($user['email']) ?></span></li>
            </ul>
        </div>

        <form method="POST" class="space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="client_id" value="<?= e($clientId) ?>">
            <input type="hidden" name="redirect_uri" value="<?= e($redirectUri) ?>">
            <input type="hidden" name="state" value="<?= e($state) ?>">

            <button type="submit" name="decision" value="allow"
                class="w-full bg-brass hover:bg-brass-bright text-ink font-display font-semibold py-3 rounded transition-colors">
                Allow
            </button>
            <button type="submit" name="decision" value="deny"
                class="w-full text-mute hover:text-paper text-sm py-2">
                Deny
            </button>
        </form>

        <p class="text-mute text-xs mt-8">
            You can revoke this anytime from your
            <a href="/dashboard.php" class="text-brass hover:text-brass-bright">account dashboard</a>.
        </p>
    </div>
</div>
<?php page_end(); ?>
