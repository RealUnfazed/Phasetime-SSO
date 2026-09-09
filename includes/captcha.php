<?php
/**
 * Self-hosted CAPTCHA. No reCAPTCHA, no hCaptcha, nothing external.
 *
 * Primary challenge: "slide the key into the lock." A small PHP-GD
 * generated image has a keyhole cut into it at a random spot. The
 * user drags a key icon along a slider until it visually lines up.
 * The target position lives ONLY in the session, never sent to the
 * browser as a number or in CSS. It's baked into image PIXELS. A bot
 * that just parses the raw HTML has nothing to read; it would need to
 * actually render and visually interpret an image to solve it, which
 * is exactly the property a text field or a DOM attribute can't give
 * you (anything expressed as inspectable markup is trivially bot-readable
 * even without rendering).
 *
 * Falls back to a short logic question if the GD extension isn't
 * available (rare, most hosts ship it, but this keeps login/register
 * from breaking outright if it's missing). Weaker, but still stops the
 * dumbest scripted form-spam.
 *
 * Layered with two more zero-friction checks that catch bots humans
 * never notice:
 *  - a honeypot field real users never see or fill in
 *  - a minimum time-on-page, since spam scripts submit instantly
 */

require_once __DIR__ . '/functions.php';

const CAPTCHA_TRACK_WIDTH    = 260;
const CAPTCHA_IMAGE_HEIGHT   = 70;
const CAPTCHA_KEY_ICON_WIDTH = 22;
const CAPTCHA_TOLERANCE_PX   = 11;
const CAPTCHA_MIN_SECONDS    = 2;
const CAPTCHA_TTL_SECONDS    = 300;
const CAPTCHA_HONEYPOT_FIELD = 'website_url'; // an innocuous name bots like to autofill

function captcha_gd_available(): bool
{
    return extension_loaded('gd') && function_exists('imagecreatetruecolor');
}

function captcha_prune_expired(): void
{
    if (empty($_SESSION['captcha_challenges'])) {
        return;
    }
    foreach ($_SESSION['captcha_challenges'] as $id => $c) {
        if ($c['expires_at'] < time()) {
            unset($_SESSION['captcha_challenges'][$id]);
        }
    }
}

/** Creates a new challenge, stores its secret server-side, returns what the template needs to render it. */
function captcha_new(): array
{
    captcha_prune_expired();
    $id = random_token(16);

    if (captcha_gd_available()) {
        $targetX = random_int(24, CAPTCHA_TRACK_WIDTH - 24);
        $_SESSION['captcha_challenges'][$id] = [
            'type'       => 'slide',
            'target_x'   => $targetX,
            'created_at' => time(),
            'expires_at' => time() + CAPTCHA_TTL_SECONDS,
        ];

        return [
            'id'        => $id,
            'type'      => 'slide',
            'image_uri' => 'data:image/png;base64,' . base64_encode(captcha_render_image($targetX)),
            'width'     => CAPTCHA_TRACK_WIDTH,
            'height'    => CAPTCHA_IMAGE_HEIGHT,
        ];
    }

    // No GD available, so fall back to a short rotating question instead
    // of breaking the login form outright.
    $questions = [
        ['q' => 'Type the word "unlock" (no quotes)', 'a' => 'unlock'],
        ['q' => 'What is three plus four?', 'a' => '7'],
        ['q' => 'How many days are in a week?', 'a' => '7'],
        ['q' => 'What color do you get mixing blue and yellow?', 'a' => 'green'],
        ['q' => 'Type the number that comes after nine', 'a' => '10'],
    ];
    $picked = $questions[array_rand($questions)];
    $_SESSION['captcha_challenges'][$id] = [
        'type'       => 'fallback',
        'answer'     => strtolower($picked['a']),
        'created_at' => time(),
        'expires_at' => time() + CAPTCHA_TTL_SECONDS,
    ];

    return [
        'id'       => $id,
        'type'     => 'fallback',
        'question' => $picked['q'],
    ];
}

function captcha_render_image(int $targetX): string
{
    $width  = CAPTCHA_TRACK_WIDTH;
    $height = CAPTCHA_IMAGE_HEIGHT;

    $img = imagecreatetruecolor($width, $height);

    // Colors matched to the site theme so the puzzle looks native, not bolted on.
    $bg     = imagecolorallocate($img, 0x17, 0x1D, 0x2E); // ink-panel
    $border = imagecolorallocate($img, 0x2A, 0x33, 0x48); // ink-border
    $brass  = imagecolorallocate($img, 0xC8, 0x9B, 0x3C); // brass
    $dark   = imagecolorallocate($img, 0x0D, 0x13, 0x21); // ink (reads as a genuine cut-out)

    imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $bg);

    // Faint scattered dots so this isn't a flat rectangle. Also makes
    // naive pixel-diffing across challenges slightly less trivial.
    for ($i = 0; $i < 16; $i++) {
        $dotColor = random_int(0, 1) ? $border : $bg;
        imagefilledellipse($img, random_int(4, $width - 4), random_int(4, $height - 4), 2, 2, $dotColor);
    }

    // The keyhole silhouette (circle + tapered triangle) at the secret X,
    // this pixel position is the only place the answer exists anywhere.
    $centerY = (int) round($height / 2) - 4;
    imagefilledellipse($img, $targetX, $centerY, 16, 16, $dark);
    imagefilledpolygon($img, [
        $targetX - 6, $centerY + 4,
        $targetX + 6, $centerY + 4,
        $targetX,     $centerY + 20,
    ], $dark);
    imageellipse($img, $targetX, $centerY, 18, 18, $brass);

    ob_start();
    imagepng($img);
    $bytes = ob_get_clean();
    imagedestroy($img);

    return $bytes;
}

/**
 * Verifies AND immediately invalidates a challenge. Single-use, no
 * matter whether it passes or fails, so a leaked answer can't be
 * replayed. Returns true only if it existed, hadn't expired, enough
 * time had passed since it was rendered (blocks instant-submit bots),
 * and the answer was close enough (image) or matched (fallback).
 */
function captcha_verify(string $id, mixed $submitted): bool
{
    $challenge = $_SESSION['captcha_challenges'][$id] ?? null;
    unset($_SESSION['captcha_challenges'][$id]);

    if (!$challenge || $challenge['expires_at'] < time()) {
        return false;
    }
    if (time() - $challenge['created_at'] < CAPTCHA_MIN_SECONDS) {
        return false; // implausibly fast for a human to have actually looked at it
    }

    if ($challenge['type'] === 'slide') {
        return is_numeric($submitted)
            && abs((float) $submitted - $challenge['target_x']) <= CAPTCHA_TOLERANCE_PX;
    }

    return is_string($submitted) && trim(strtolower($submitted)) === $challenge['answer'];
}

function honeypot_passed(): bool
{
    return empty($_POST[CAPTCHA_HONEYPOT_FIELD]);
}

/** Renders the widget's HTML + inline JS. Pass in the array captcha_new() returned. */
function captcha_widget_html(array $captcha): void
{
    $maxSlide = (int) $captcha['width'] - (captcha_gd_available() ? CAPTCHA_KEY_ICON_WIDTH : 0);
    ?>
    <div>
        <input type="hidden" name="captcha_id" value="<?= e($captcha['id']) ?>">
        <!-- Honeypot: invisible to a real person, irresistible to a bot that blindly fills every input it finds. -->
        <input type="text" name="<?= e(CAPTCHA_HONEYPOT_FIELD) ?>" value="" tabindex="-1" autocomplete="off"
               style="position:absolute;left:-10000px;top:-10000px" aria-hidden="true">

        <?php if ($captcha['type'] === 'slide'): ?>
            <label class="block text-xs text-mute mb-2">Slide the key until it lines up with the lock</label>
            <div class="relative select-none" style="width:<?= (int) $captcha['width'] ?>px; max-width:100%;">
                <img src="<?= $captcha['image_uri'] ?>" width="<?= (int) $captcha['width'] ?>" height="<?= (int) $captcha['height'] ?>"
                     alt="Slide the key to the keyhole" class="rounded border border-ink-border block" draggable="false">
                <div id="captcha-key-<?= e($captcha['id']) ?>" class="absolute top-1/2 -translate-y-1/2 pointer-events-none" style="left:0px;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="7" cy="7" r="4.5" stroke="#DDB35C" stroke-width="2"/>
                        <rect x="10.5" y="6" width="10" height="2" fill="#DDB35C"/>
                        <rect x="17" y="8" width="2" height="3" fill="#DDB35C"/>
                        <rect x="20" y="8" width="2" height="3" fill="#DDB35C"/>
                    </svg>
                </div>
            </div>
            <input type="range" name="captcha_answer" min="0" max="<?= $maxSlide ?>" value="0"
                   class="w-full mt-2 accent-brass" style="max-width:<?= (int) $captcha['width'] ?>px"
                   oninput="document.getElementById('captcha-key-<?= e($captcha['id']) ?>').style.left = this.value + 'px'">
        <?php else: ?>
            <label class="block text-xs text-mute mb-1"><?= e($captcha['question']) ?></label>
            <input class="field" type="text" name="captcha_answer" required autocomplete="off">
        <?php endif; ?>
    </div>
    <?php
}
