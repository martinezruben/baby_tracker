<?php

// Genera los iconos de la PWA (php scripts/make-icons.php). Sin dependencias: solo GD.

function icon(int $size, float $glyph, bool $rounded): GdImage
{
    $s = $size * 4; // sobremuestreo para bordes suaves
    $im = imagecreatetruecolor($s, $s);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);

    $teal = imagecolorallocate($im, 8, 120, 115);
    $white = imagecolorallocate($im, 255, 255, 255);

    if ($rounded) {
        $r = (int) ($s * 0.22);
        imagefilledrectangle($im, $r, 0, $s - $r, $s, $teal);
        imagefilledrectangle($im, 0, $r, $s, $s - $r, $teal);
        foreach ([[$r, $r], [$s - $r, $r], [$r, $s - $r], [$s - $r, $s - $r]] as [$x, $y]) {
            imagefilledellipse($im, $x, $y, $r * 2, $r * 2, $teal);
        }
    } else {
        imagefilledrectangle($im, 0, 0, $s, $s, $teal);
    }

    $c = $s / 2;
    $d = (int) ($s * $glyph);
    imagefilledellipse($im, (int) $c, (int) $c, $d, $d, $white);

    $eye = (int) ($d * 0.09);
    imagefilledellipse($im, (int) ($c - $d * 0.2), (int) ($c - $d * 0.05), $eye, $eye, $teal);
    imagefilledellipse($im, (int) ($c + $d * 0.2), (int) ($c - $d * 0.05), $eye, $eye, $teal);
    imagesetthickness($im, (int) ($d * 0.05));
    imagearc($im, (int) $c, (int) ($c + $d * 0.08), (int) ($d * 0.36), (int) ($d * 0.28), 20, 160, $teal);
    // mechón de pelo
    imagesetthickness($im, (int) ($d * 0.05));
    imagearc($im, (int) $c, (int) ($c - $d * 0.5), (int) ($d * 0.22), (int) ($d * 0.2), 200, 340, $white);

    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $size, $size, $s, $s);

    return $out;
}

$dir = __DIR__.'/../public/icons';
foreach ([
    'icon-192.png' => [192, 0.62, true],
    'icon-512.png' => [512, 0.62, true],
    'maskable-512.png' => [512, 0.46, false], // el glifo cabe en la zona segura (80 %)
    'apple-touch-icon.png' => [180, 0.62, false], // iOS aplica su propia máscara
] as $name => [$size, $glyph, $rounded]) {
    imagepng(icon($size, $glyph, $rounded), "$dir/$name");
}
