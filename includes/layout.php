<?php
/**
 * Tiny layout helpers so every page shares the same <head> and fonts
 * without a templating engine. Call page_start() then page_end().
 */

require_once __DIR__ . '/functions.php';

function page_start(string $title, string $bodyClass = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="/assets/js/tw-config.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body class="bg-ink text-paper font-body min-h-screen <?= e($bodyClass) ?>">
<?php
}

function page_end(): void
{
    ?>
</body>
</html>
<?php
}

/** A dismissible-looking (but static) flash message block. */
function render_flash(?string $message, string $type = 'error'): void
{
    if (!$message) {
        return;
    }
    $color = $type === 'error' ? 'rust' : 'teal';
    ?>
    <div class="border border-<?= $color ?> bg-<?= $color ?>/10 text-<?= $color ?>-bright text-sm px-4 py-3 rounded mb-6">
        <?= e($message) ?>
    </div>
    <?php
}
