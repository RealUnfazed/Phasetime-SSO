# Phasetime

Self-hosted single sign-on for a pile of small projects that all need login and none of them deserve their own auth system.

By [Alireza](https://github.com/realunfazed). MIT licensed, see [LICENSE](LICENSE).

Register once. Every mini-project trusts that login instead of shipping its own signup form, password table, and "forgot password" flow.

It's the same pattern behind "Sign in with Google," an OAuth2-style Authorization Code flow. That's why it still works when your projects live on completely different domains, which a shared cookie alone can't do.

---

## How it works

```
 Your browser                Mini-project A               Phasetime (SSO server)
 ─────────────               ───────────────               ───────────────────────
      │  1. visit a protected page  │                              │
      │ ───────────────────────────>│                              │
      │                              │ 2. no local session, send    │
      │                              │    browser to /authorize     │
      │ <───────────────────────────│                              │
      │  3. GET /authorize.php ─────────────────────────────────────>
      │                              │                              │ logged in already?
      │                              │                              │  no -> show login form
      │  4. user logs in (once) ─────────────────────────────────────>
      │                              │                              │ set SSO session cookie
      │  4b. FIRST time only: "X wants to sign you in, Allow/Deny" ─>│
      │      approve ──────────────────────────────────────────────>│ store consent
      │                              │                              │ issue one-time code
      │  5. redirect back with ?code=... ───────────────────────────>│
      │ <──────────────────────────────────────────────────────────│
      │  6. browser hits Mini-project A's callback.php with the code│
      │ ───────────────────────────>│                              │
      │                              │ 7. server-to-server: trade   │
      │                              │    code + client_secret for  │
      │                              │    an access token           │──> POST /token.php
      │                              │ 8. fetch the profile         │──> GET /userinfo.php
      │                              │ 9. start local session       │
      │ <───────────────────────────│                              │
      │  10. logged into Mini-project A                             │
```

Now visit Mini-project B. It redirects to `/authorize.php` too, so you get a consent screen once (a fresh app, a fresh ask). But step 4, typing your password, gets skipped entirely: Phasetime already has a session for you. That's the "single" part. Approve both A and B once, and going back to either skips the password and the consent screen, until you revoke one from your dashboard.

---

## Requirements

- PHP 8.1+ with `pdo_mysql` and `curl`
- `gd` recommended (used for the captcha image, falls back to a text question if missing)
- MySQL 5.7+ / MariaDB
- HTTPS in production: cookies and bearer tokens both need it

## Setup

Flat layout on purpose: upload everything in this folder straight into whatever already serves your domain (`public_html` or equivalent). No document root changes, no vhost editing. `config/`, `includes/`, `database/`, `bin/`, `sdk/`, `examples/` all ship with `.htaccess` files denying direct access. Worth checking this actually works after deploying by visiting `yourdomain.com/config/config.php`. Blank page or 403 means you're fine. Raw PHP text means `AllowOverride` isn't on, and it's worth asking your host about that.

1. **Create the database and import the schema.**
   ```
   mysql -u root -p < database/schema.sql
   ```
   Already running an earlier version, from before consent/revoke existed? Don't re-import that. Run `database/migration_add_consents.sql` instead since it just adds the one table.

   No root login? Make an empty database through whatever tool your host gives you (phpMyAdmin, usually), delete the first two lines of `schema.sql` (the `CREATE DATABASE` and `USE` lines), then:
   ```
   mysql -u your_db_user -p your_db_name < database/schema.sql
   ```

2. **Copy the config template and fill it in.**
   ```
   cp config/config.example.php config/config.php
   ```
   `config.php` is gitignored: your real DB password and real domain live there, never in version control. Set the `db` section and `base_url`.

3. **Upload everything** to your web root, as-is.

4. **Create your admin account**, one of two ways:

   - No shell access: visit `yourdomain.com/setup.php`, fill in your name, email, and a password of your own (the password field starts with a random placeholder, replace it before submitting), then delete `setup.php` from the server. It won't run a second time once an admin exists, but it's still a script sitting there whose entire job is making admin accounts. Better gone.
   - Shell access:
     ```
     php bin/create_admin.php
     ```

5. Visit `yourdomain.com/admin/` and sign in.

Testing locally over plain `http://`? Set `cookie_secure` to `false` in `config/config.php`. Browsers won't set `Secure` cookies without HTTPS, so the session just won't stick otherwise.

---

## Connecting a mini-project

**1. Register it.** Admin panel → Client apps → register a new one. You'll get a `client_id` and `client_secret`. Copy the secret now. It's shown again later if you forget, but treat it like a password: server-side config only, never JS, never a public repo.

**2. Copy the SDK.** Drop `sdk/SSOClient.php` into the project.

**3. Add the callback file**, at the exact URL you registered:

```php
// sso/callback.php
require __DIR__ . '/../SSOClient.php';

$sso = new SSOClient([
    'sso_base_url'  => 'https://sso.example.com',
    'client_id'     => 'cid_xxxxxxxxxxxx',
    'client_secret' => 'the_secret_from_step_1',
    'redirect_uri'  => 'https://myproject.com/sso/callback.php',
]);
$sso->handleCallback();
```

**4. Protect any page** with one line:

```php
require __DIR__ . '/SSOClient.php';
$sso = new SSOClient([...]); // same config as above
$user = $sso->requireLogin(); // redirects to Phasetime if not logged in

echo "Hello, {$user['name']}"; // ['id', 'name', 'email']
```

**5. Log out.** Two options here, and it matters which one you pick.

```php
$sso->logout('/goodbye.php');
```

Clears only this app's local session. Everyone else stays signed in, same as logging out of one Google product not touching the others. This is what a normal logout button should call, and it never even talks to the Phasetime server.

Want a real "sign out of everything" button instead, one that ends the shared session so the next login *anywhere* asks for a password again?

```php
$sso->logoutEverywhere('https://myproject.com/goodbye.php');
```

Register that URL as the client's Logout URL in the admin panel first if you want to land back on your own page instead of Phasetime's default goodbye screen. `logoutEverywhere()` only redirects there if it matches exactly.

> Upgrading from an older copy of the SDK: `logout()` used to end the central session by default. Not anymore. It's local-only now, and `logoutEverywhere()` is the one that ends everything. Update your copy of `SSOClient.php`, and swap in `logoutEverywhere()` anywhere you actually wanted the old behavior.

Full working example (homepage, protected page, callback, logout) in `examples/demo-client/`.

---

## Consent and revoking access

First time someone signs into a given client app, they see a screen naming it and exactly what it'll see (name, email), and they have to hit Allow. Deny sends the client `?error=access_denied` instead of a code. `handleCallback()` already handles that and shows a plain "you declined" message.

That decision sticks, which is why coming back to an app you've already approved skips straight through: no password, no screen. It also means you can take it back. Every approved app shows up under **Connected apps** on `/dashboard.php`, with a Revoke button. Revoking deletes the stored consent and kills that app's current tokens.

What it doesn't do: end a session the app already started locally. If you're using Mini-project A in another tab when you revoke it, A keeps running off its own session until it needs to check back in. That's the same limitation any SSO without a session check on every page load has. An app that wants tighter guarantees can just call `/userinfo.php` more often instead of trusting its local session forever; that's a call each mini-project makes for itself.

---

## Endpoint reference

The browser hits `/authorize.php` and `/logout.php`. Your backend calls `/token.php` and `/userinfo.php` directly, server to server.

| Endpoint | Called from | Purpose |
|---|---|---|
| `GET/POST /authorize.php` | browser | Starts login; shows a consent screen the first time a user meets a given client, then redirects back with a one-time code |
| `POST /token.php` | client backend | Exchanges a code (or refresh token) for an access token |
| `GET /userinfo.php` | client backend | Returns `{id, name, email}` for a valid Bearer token |
| `GET /logout.php` | browser | Ends the central SSO session |
| `GET /login.php`, `/register.php` | browser | The login/signup forms |
| `GET /dashboard.php` | browser | The user's own account page on the SSO server |

`/token.php` request body (`application/x-www-form-urlencoded`):

```
grant_type=authorization_code
client_id=cid_xxxx
client_secret=xxxx
code=xxxx
redirect_uri=https://myproject.com/sso/callback.php
```

Response:
```json
{"access_token":"...","refresh_token":"...","token_type":"Bearer","expires_in":3600}
```

---

## Security notes

- HTTPS everywhere. Cookies are `Secure`, and access tokens are bearer tokens with no other proof of possession. Both need TLS.
- `redirect_uri` matched exactly, byte for byte. Don't loosen this to a prefix match. That's the classic hole in homemade OAuth.
- `client_secret` never touches the browser, only the server-to-server `/token.php` call. If one leaks, delete that client in the admin panel and register it again.
- Auth codes are single-use, expire in 60 seconds.
- 5 failed logins locks an account for 15 minutes (tune this in `config.php`). Admins can unlock early from Users.
- Passwords hashed with bcrypt, never stored or logged in plain text.
- `setup.php` refuses to run twice once an admin exists. Delete it anyway once you're done.
- CSRF tokens on every form that changes state.
- CAPTCHA on login and register, self-hosted, no third party.

### About the captcha

`login.php` and `register.php` show a small PHP-drawn image with a keyhole cut into it at a random spot, and you drag a key along a slider until it lines up. The target position only ever exists in your session, baked into pixels, never sent as a number or a CSS value anywhere in the page. A script that just downloads and parses the HTML has nothing to read; it'd have to actually render and look at an image.

Two more checks ride along, invisible to a real person:
- a honeypot field (`website_url`), positioned off-screen, that bots filling every input on a form tend to trip
- a 2-second minimum between the page loading and the form being submitted, since spam scripts tend to submit instantly

All three collapse into the same generic "that didn't look right" error, so nothing tells a bot which check it hit.

No `gd` extension installed? Falls back automatically to a short rotating logic question instead of breaking the page. Weaker (a determined bot could technically read and answer plain text), but it still stops the scripted form-spam that's the realistic threat here.

Tune it via the constants at the top of `includes/captcha.php` (`CAPTCHA_TOLERANCE_PX`, `CAPTCHA_MIN_SECONDS`, `CAPTCHA_TTL_SECONDS`).

### What's missing, on purpose, for v1

- **Password reset via email.** No mailer assumed here. Add a `password_resets` table (token + expiry) and wire up whatever mail service you like. `auth.php` is set up to make that a small addition.
- **True single logout.** `logoutEverywhere()` kills the central session, so the next login anywhere asks for a password again, but an app you're still actively using in another tab keeps running off its own session until it checks back in. Real push-based logout would need each client to expose a webhook the server can call on sign-out. Reasonable to add later if you need it.
- **ID tokens / OpenID Connect.** `/userinfo.php` covers "who is this" for a handful of self-owned apps. A signed JWT is the natural next layer if you ever need one.

---

## Folder structure

Flat on purpose. Upload this whole folder as-is to your web root:

```
phasetime/                     ← this whole folder = your web root
├── LICENSE  SECURITY.md  CONTRIBUTING.md  .gitignore  README.md
├── setup.php                   (one-time web installer, delete after use)
├── login.php  register.php  authorize.php
├── token.php  userinfo.php  logout.php
├── dashboard.php  index.php
├── assets/                     (tailwind config + theme css)
├── admin/                      ← client-app & user management panel
│
├── config/config.example.php   ← tracked template, safe to commit
├── config/config.php           ← your real settings, gitignored, never commit  (blocked via .htaccess)
├── includes/                   ← db.php, functions.php, auth.php, layout.php, captcha.php  (blocked)
├── database/schema.sql         ← import this first  (blocked)
├── database/migration_add_consents.sql  ← for installs from before consent/revoke existed  (blocked)
├── bin/create_admin.php        ← optional CLI alternative to setup.php  (blocked)
├── sdk/SSOClient.php           ← copy this into each mini-project  (blocked)
└── examples/demo-client/       ← reference integration, for a different domain than this one  (blocked)
```

"Blocked" means `.htaccess` denies direct web access to that folder. Those five don't hold anything a browser needs, just files the top-level scripts pull in server-side.
