<?php

// Genera el carrito recortado y los iconos de la PWA desde resources/brand/kidu-logo.png
// (php scripts/make-icons.php). Solo requiere GD.

$src = imagecreatefrompng(__DIR__.'/../resources/brand/kidu-logo.png');
$bg = imagecolorat($src, 4, 4); // gris cálido del fondo del logo
[$br, $bgc, $bb] = [($bg >> 16) & 255, ($bg >> 8) & 255, $bg & 255];

/** Copia una región del logo; con $transparent el fondo pasa a alfa. */
function crop(GdImage $src, int $x, int $y, int $w, int $h, bool $transparent, array $bg): GdImage
{
    $out = imagecreatetruecolor($w, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    for ($j = 0; $j < $h; $j++) {
        for ($i = 0; $i < $w; $i++) {
            $c = imagecolorat($src, $x + $i, $y + $j);
            [$r, $g, $b] = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
            $alpha = 0;
            if ($transparent) {
                $d = max(abs($r - $bg[0]), abs($g - $bg[1]), abs($b - $bg[2]));
                $alpha = 127 - (int) min(127, max(0, $d - 6) * 8);
            }
            imagesetpixel($out, $i, $j, imagecolorallocatealpha($out, $r, $g, $b, $alpha));
        }
    }

    return $out;
}

function save(GdImage $im, string $path): void
{
    imagepng($im, $path);
}

$dir = __DIR__.'/../public';
$bgArr = [$br, $bgc, $bb];

// Solo el carrito (el nombre "Kidu Baby Tracker" va como texto), con fondo transparente
$mark = crop($src, 380, 115, 275, 270, true, $bgArr);
save($mark, "$dir/brand/kidu-mark.png");

/** Icono cuadrado: carrito centrado sobre el color del logo. */
function icon(GdImage $src, array $bg, int $size, float $glyph, bool $rounded): GdImage
{
    $s = $size * 2;
    $im = imagecreatetruecolor($s, $s);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    $col = imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
    if ($rounded) {
        $r = (int) ($s * 0.22);
        imagefilledrectangle($im, $r, 0, $s - $r, $s, $col);
        imagefilledrectangle($im, 0, $r, $s, $s - $r, $col);
        foreach ([[$r, $r], [$s - $r, $r], [$r, $s - $r], [$s - $r, $s - $r]] as [$x, $y]) {
            imagefilledellipse($im, $x, $y, $r * 2, $r * 2, $col);
        }
    } else {
        imagefilledrectangle($im, 0, 0, $s, $s, $col);
    }
    imagealphablending($im, true);

    $mw = imagesx($src);
    $mh = imagesy($src);
    $w = (int) ($s * $glyph);
    $h = (int) ($w * $mh / $mw);
    imagecopyresampled($im, $src, (int) (($s - $w) / 2), (int) (($s - $h) / 2), 0, 0, $w, $h, $mw, $mh);

    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $size, $size, $s, $s);

    return $out;
}

foreach ([
    'icon-192.png' => [192, 0.62, true],
    'icon-512.png' => [512, 0.62, true],
    'maskable-512.png' => [512, 0.5, false], // dentro de la zona segura (80 %)
    'apple-touch-icon.png' => [180, 0.62, false], // iOS aplica su propia máscara
] as $name => [$size, $glyph, $rounded]) {
    save(icon($mark, $bgArr, $size, $glyph, $rounded), "$dir/icons/$name");
}

printf("Fondo del logo: #%02X%02X%02X\n", $br, $bgc, $bb);
