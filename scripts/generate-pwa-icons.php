<?php

declare(strict_types=1);

$outDir = __DIR__.'/../public/icons';
$logoPath = __DIR__.'/../public/images/human-logo.png';

function loadLogoImage(string $path): ?\GdImage
{
    if (! is_file($path)) {
        return null;
    }

    $info = @getimagesize($path);
    if ($info === false) {
        return null;
    }

    return match ($info[2]) {
        IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path) ?: null,
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
        default => null,
    };
}

function makeIconFromLogo(?\GdImage $logo, int $size, string $path, float $paddingRatio = 0.1): void
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);

    // Site logosu ile aynı krem zemin (#F4EFE8)
    $bg = imagecolorallocate($img, 244, 239, 232);
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);

    if ($logo instanceof \GdImage) {
        $lw = imagesx($logo);
        $lh = imagesy($logo);
        $padding = (int) round($size * $paddingRatio);
        $max = max(1, $size - ($padding * 2));
        $scale = min($max / $lw, $max / $lh);
        $nw = max(1, (int) round($lw * $scale));
        $nh = max(1, (int) round($lh * $scale));
        $dx = (int) round(($size - $nw) / 2);
        $dy = (int) round(($size - $nh) / 2);
        imagecopyresampled($img, $logo, $dx, $dy, 0, 0, $nw, $nh, $lw, $lh);
    } else {
        drawFallbackMark($img, $size);
    }

    imagepng($img, $path);
    imagedestroy($img);
}

function drawFallbackMark(\GdImage $img, int $size): void
{
    $orange = imagecolorallocate($img, 183, 133, 23);
    $cx = (int) ($size / 2);
    $r = (int) ($size * 0.12);
    imagefilledellipse($img, $cx, (int) ($size * 0.38), $r * 2, $r * 2, $orange);
    imagefilledellipse($img, $cx, (int) ($size * 0.68), (int) ($size * 0.28), (int) ($size * 0.16), $orange);
}

if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$logo = loadLogoImage($logoPath);
if ($logo === null) {
    echo "Warning: public/images/human-logo.png not found — using fallback mark.\n";
}

makeIconFromLogo($logo, 16, $outDir.'/favicon-16.png');
makeIconFromLogo($logo, 32, $outDir.'/favicon-32.png');
makeIconFromLogo($logo, 180, $outDir.'/apple-touch-icon.png');
makeIconFromLogo($logo, 192, $outDir.'/icon-192.png');
makeIconFromLogo($logo, 256, $outDir.'/icon-256.png');
makeIconFromLogo($logo, 512, $outDir.'/icon-512.png');
// Maskable: logo güvenli alanda (~%20 kenar boşluğu)
makeIconFromLogo($logo, 512, $outDir.'/icon-512-maskable.png', 0.2);

copy($outDir.'/favicon-32.png', __DIR__.'/../public/favicon.ico');

if ($logo instanceof \GdImage) {
    imagedestroy($logo);
}

echo "PWA icons generated from site logo → public/icons/\n";
