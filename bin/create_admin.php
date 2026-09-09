<?php
/**
 * Run once, from the command line, to create (or promote) an admin account:
 *
 *   php bin/create_admin.php
 *
 * Deliberately NOT a web page. Creating admins over HTTP is how you end
 * up with an admin-creation endpoint someone forgets to remove.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../includes/auth.php';

function prompt(string $label): string
{
    echo $label;
    return trim(fgets(STDIN));
}

function prompt_password(string $label): string
{
    echo $label;
    // Best-effort hide input on Unix-like systems; falls back to plain input on Windows.
    if (stripos(PHP_OS, 'WIN') === false) {
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
    } else {
        $password = trim(fgets(STDIN));
    }
    return $password;
}

echo "== Phasetime: create admin account ==\n\n";

$email = prompt('Email: ');
$existing = find_user_by_email($email);

if ($existing) {
    if ($existing['is_admin']) {
        die("That user is already an admin.\n");
    }
    $confirm = prompt("User already exists as a regular account. Promote to admin? [y/N]: ");
    if (strtolower($confirm) !== 'y') {
        die("Cancelled.\n");
    }
    db()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$existing['id']]);
    echo "Done. {$email} is now an admin.\n";
    exit;
}

$name = prompt('Name: ');
$password = prompt_password('Password (min 8 chars): ');
$confirmPassword = prompt_password('Confirm password: ');

if (strlen($password) < 8) {
    die("Password must be at least 8 characters.\n");
}
if ($password !== $confirmPassword) {
    die("Passwords don't match.\n");
}
if (!validate_email($email)) {
    die("That doesn't look like a valid email.\n");
}

$user = create_user($name, $email, $password);
db()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$user['id']]);

echo "\nDone. {$email} can now sign in and reach /admin/.\n";
