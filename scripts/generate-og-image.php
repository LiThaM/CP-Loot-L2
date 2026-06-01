<?php
/**
 * One-shot generator for /public/og-image.png — the social share preview.
 * Run with: php scripts/generate-og-image.php
 *
 * 1200×630 (Facebook / OpenGraph spec), purple→blue→amber gradient
 * background, centred "AdenaLedger" wordmark in white + tagline + tiny
 * decorative dots. Pure PHP GD so we don't depend on imagemagick or
 * external services. Re-run any time copy changes.
 */

$width = 1200;
$height = 630;
$img = imagecreatetruecolor($width, $height);

// --- Background gradient (vertical purple → black → amber sliver) ---
$dark = imagecolorallocate($img, 10, 10, 15);      // #0a0a0f
$purple = imagecolorallocate($img, 88, 28, 135);   // purple-800
$midPurple = imagecolorallocate($img, 31, 14, 50);
$amber = imagecolorallocate($img, 217, 119, 6);    // amber-600
$white = imagecolorallocate($img, 255, 255, 255);
$gray = imagecolorallocate($img, 200, 200, 215);
$mutedGray = imagecolorallocate($img, 156, 163, 175);

// Vertical gradient
for ($y = 0; $y < $height; $y++) {
    $t = $y / $height;
    // Lerp through three stops: purple → dark → dark
    if ($t < 0.5) {
        $r = (int) (28 + (10 - 28) * ($t / 0.5));
        $g = (int) (14 + (10 - 14) * ($t / 0.5));
        $b = (int) (50 + (15 - 50) * ($t / 0.5));
    } else {
        $r = 10;
        $g = 10;
        $b = 15;
    }
    $color = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, $width, $y, $color);
}

// --- Decorative "spotlight" blur dots (filled ellipses with alpha) ---
$purpleGlow = imagecolorallocatealpha($img, 168, 85, 247, 100);  // soft purple
$amberGlow = imagecolorallocatealpha($img, 245, 158, 11, 110);   // soft amber
imagefilledellipse($img, 200, 200, 700, 500, $purpleGlow);
imagefilledellipse($img, 1100, 500, 500, 400, $amberGlow);

// --- Border highlight (subtle 1px frame) ---
$borderTint = imagecolorallocatealpha($img, 255, 255, 255, 110);
imagerectangle($img, 8, 8, $width - 9, $height - 9, $borderTint);

// --- Font path: macOS bundles Arial; on Linux servers fall back to DejaVu ---
$candidates = [
    '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
    '/System/Library/Fonts/Helvetica.ttc',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
];
$boldFont = null;
foreach ($candidates as $f) {
    if (is_readable($f)) { $boldFont = $f; break; }
}
if (!$boldFont) {
    // Bail out with builtin bitmap so the image isn't empty.
    imagestring($img, 5, 480, 280, 'AdenaLedger', $white);
    imagestring($img, 3, 410, 320, 'Loot / Adena / Warehouse for L2 CPs', $mutedGray);
} else {
    // Big wordmark
    $title = 'AdenaLedger';
    $titleSize = 96;
    $bbox = imagettfbbox($titleSize, 0, $boldFont, $title);
    $titleW = $bbox[2] - $bbox[0];
    $titleX = (int) (($width - $titleW) / 2);
    $titleY = 320;
    imagettftext($img, $titleSize, 0, $titleX, $titleY, $white, $boldFont, $title);

    // Tagline
    $tag = 'Loot · Adena · Warehouse for Lineage II CPs';
    $tagSize = 32;
    $bbox = imagettfbbox($tagSize, 0, $boldFont, $tag);
    $tagW = $bbox[2] - $bbox[0];
    $tagX = (int) (($width - $tagW) / 2);
    $tagY = $titleY + 60;
    imagettftext($img, $tagSize, 0, $tagX, $tagY, $gray, $boldFont, $tag);

    // Eyebrow chip "FREE · NO ADS · NO TRACKING"
    $eyebrow = 'FREE · NO ADS · NO TRACKING';
    $ebSize = 18;
    $bbox = imagettfbbox($ebSize, 0, $boldFont, $eyebrow);
    $ebW = $bbox[2] - $bbox[0];
    $ebX = (int) (($width - $ebW) / 2);
    $ebY = $titleY - 110;
    imagettftext($img, $ebSize, 0, $ebX, $ebY, $amber, $boldFont, $eyebrow);
}

$out = __DIR__ . '/../public/og-image.png';
imagepng($img, $out);
imagedestroy($img);
echo "Wrote $out\n";
