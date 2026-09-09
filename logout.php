<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
session_start();

destroy_sso_session();

// A client app can send the user back to itself after central logout, but
// only to the exact logout_uri it registered, same anti-open-redirect
// rule as /authorize.php, just checked against logout_uri instead.
$clientId = $_GET['client_id'] ?? '';
$redirectUri = $_GET['redirect_uri'] ?? '';
if ($clientId && $redirectUri) {
    $client = find_client($clientId);
    if ($client && $client['logout_uri'] && redirect_uri_is_valid($client['logout_uri'], $redirectUri)) {
        redirect($redirectUri);
    }
    // Fall through to the generic goodbye page if validation fails.
    // never redirect to an unverified address.
}

$next = $_GET['next'] ?? null;
if ($next) {
    redirect(safe_next($next, '/login.php'));
}

page_start('Signed out');
?>
<div class="flex min-h-screen items-center justify-center p-8">
    <div class="max-w-sm text-center">
        <h1 class="font-display text-xl font-semibold mb-2">You're signed out</h1>
        <p class="text-mute text-sm mb-6">
            Your Phasetime session has ended. Individual apps you were signed into may keep their
            own local session until you also log out of them, or until it expires.
        </p>
        <a href="/login.php" class="text-brass hover:text-brass-bright text-sm">Sign in again</a>
    </div>
</div>
<?php page_end(); ?>
