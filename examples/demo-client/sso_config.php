<?php
/**
 * Fill these in after registering "Demo Client" in the admin panel
 * (Admin → Client apps → Register a new client app).
 *
 * redirect_uri here must be typed EXACTLY as you registered it, including
 * the scheme and path, e.g. https://demo.example.com/sso/callback.php
 */
return [
    'sso_base_url'  => 'https://sso.example.com', // <- your real SSO server's URL
    'client_id'     => 'cid_replace_me',
    'client_secret' => 'replace_me_with_the_secret_shown_at_registration',
    'redirect_uri'  => 'https://demo.example.com/sso/callback.php',
];
