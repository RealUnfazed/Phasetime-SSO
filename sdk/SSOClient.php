<?php
/**
 * SSOClient. Drop this one file into any mini-project to integrate
 * with your Phasetime SSO server.
 *
 * Minimal usage in a protected page:
 *
 *     require 'SSOClient.php';
 *     $sso = new SSOClient([
 *         'sso_base_url'  => 'https://sso.yourdomain.com',
 *         'client_id'     => 'cid_xxxxx',
 *         'client_secret' => 'xxxxxxxxxxxxxxxx',
 *         'redirect_uri'  => 'https://myproject.com/sso/callback.php',
 *     ]);
 *     $user = $sso->requireLogin(); // redirects to SSO if not logged in
 *     echo "Hello, {$user['name']}";
 *
 * And in sso/callback.php (the exact URL you registered as redirect_uri):
 *
 *     require '../SSOClient.php';
 *     $sso = new SSOClient([...]);
 *     $sso->handleCallback(); // exchanges the code, stores the session, redirects home
 */

class SSOClient
{
    private string $ssoBaseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct(array $config)
    {
        foreach (['sso_base_url', 'client_id', 'client_secret', 'redirect_uri'] as $key) {
            if (empty($config[$key])) {
                throw new InvalidArgumentException("SSOClient config is missing '{$key}'");
            }
        }

        $this->ssoBaseUrl   = rtrim($config['sso_base_url'], '/');
        $this->clientId     = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->redirectUri  = $config['redirect_uri'];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /** The current locally-logged-in user, or null. Cheap, no network call. */
    public function user(): ?array
    {
        return $_SESSION['sso_user'] ?? null;
    }

    /**
     * Call at the top of any page that needs a logged-in user.
     * Returns the user array if already logged in locally, otherwise
     * redirects the browser to the SSO server and does not return.
     */
    public function requireLogin(): array
    {
        $user = $this->user();
        if ($user !== null) {
            return $user;
        }
        $this->login();
    }

    /**
     * Send the browser to the SSO server to authenticate.
     * If the user already has a live SSO session elsewhere, they'll be
     * bounced straight back here with no login form shown at all.
     */
    public function login(?string $returnTo = null): never
    {
        $_SESSION['sso_return_to'] = $returnTo ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $state = bin2hex(random_bytes(16));
        $_SESSION['sso_state'] = $state;

        $url = $this->ssoBaseUrl . '/authorize.php?' . http_build_query([
            'client_id'    => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state'        => $state,
        ]);

        header('Location: ' . $url, true, 302);
        exit;
    }

    /**
     * Call this on the exact page you registered as redirect_uri.
     * Exchanges the auth code for tokens, fetches the profile, stores
     * both in the local session, then redirects back to wherever the
     * user originally wanted to go.
     */
    public function handleCallback(): never
    {
        $state = $_GET['state'] ?? '';
        $stateMatches = isset($_SESSION['sso_state']) && hash_equals($_SESSION['sso_state'], $state);

        if (isset($_GET['error'])) {
            unset($_SESSION['sso_state']);
            if ($_GET['error'] === 'access_denied') {
                http_response_code(403);
                die('You declined to share your account, so you were not signed in.');
            }
            http_response_code(400);
            die('Login failed: ' . htmlspecialchars($_GET['error']));
        }

        $code = $_GET['code'] ?? '';
        if (!$code || !$stateMatches) {
            http_response_code(400);
            die('Login could not be verified. Please try again.');
        }
        unset($_SESSION['sso_state']);

        $tokens = $this->exchangeCodeForToken($code);
        if (isset($tokens['error'])) {
            http_response_code(400);
            die('Login failed: ' . htmlspecialchars($tokens['error']));
        }

        $profile = $this->fetchUserInfo($tokens['access_token']);
        if (isset($profile['error'])) {
            http_response_code(400);
            die('Could not load your profile: ' . htmlspecialchars($profile['error']));
        }

        $_SESSION['sso_user'] = $profile;
        $_SESSION['sso_tokens'] = [
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at'    => time() + $tokens['expires_in'],
        ];

        $returnTo = $_SESSION['sso_return_to'] ?? '/';
        unset($_SESSION['sso_return_to']);

        header('Location: ' . $returnTo, true, 302);
        exit;
    }

    /**
     * Clears ONLY this app's local session. Does not touch the central
     * Phasetime session at all. The user stays signed into any other
     * connected app they're using. This is what a normal "Log out"
     * button in your app should call, the same way logging out of one
     * Google product doesn't sign you out of every other Google product.
     */
    public function logout(?string $returnTo = null): never
    {
        unset($_SESSION['sso_user'], $_SESSION['sso_tokens']);
        header('Location: ' . ($returnTo ?? '/'), true, 302);
        exit;
    }

    /**
     * Clears this app's local session AND ends the central Phasetime
     * session, so the next login ANYWHERE asks for a password (and, for
     * apps being visited fresh, consent) again. This is a "sign out of
     * everything" action, so reserve it for a button that says exactly
     * that, not a default per-app logout link.
     *
     * Pass a $returnTo URL matching the Logout URL you registered for
     * this client in the admin panel to land the user back here
     * afterward, instead of Phasetime's own goodbye page.
     */
    public function logoutEverywhere(?string $returnTo = null): never
    {
        unset($_SESSION['sso_user'], $_SESSION['sso_tokens']);

        $params = ['client_id' => $this->clientId];
        if ($returnTo) {
            $params['redirect_uri'] = $returnTo;
        }

        header('Location: ' . $this->ssoBaseUrl . '/logout.php?' . http_build_query($params), true, 302);
        exit;
    }

    // ---------------------------------------------------------------
    // Internal: server-to-server calls (never exposed to the browser)
    // ---------------------------------------------------------------

    private function exchangeCodeForToken(string $code): array
    {
        return $this->post($this->ssoBaseUrl . '/token.php', [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri,
        ]);
    }

    /** Call this yourself later if you need to refresh an expired access token. */
    public function refreshToken(): array
    {
        $refresh = $_SESSION['sso_tokens']['refresh_token'] ?? null;
        if (!$refresh) {
            return ['error' => 'no_refresh_token'];
        }

        $tokens = $this->post($this->ssoBaseUrl . '/token.php', [
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refresh,
        ]);

        if (!isset($tokens['error'])) {
            $_SESSION['sso_tokens'] = [
                'access_token'  => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at'    => time() + $tokens['expires_in'],
            ];
        }

        return $tokens;
    }

    private function fetchUserInfo(string $accessToken): array
    {
        $ch = curl_init($this->ssoBaseUrl . '/userinfo.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response ?: '{}', true) ?? ['error' => 'invalid_response'];
    }

    private function post(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return ['error' => 'connection_failed'];
        }
        curl_close($ch);

        return json_decode($response, true) ?? ['error' => 'invalid_response'];
    }
}
