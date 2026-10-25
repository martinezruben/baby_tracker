<?php

namespace Tests\Support;

/** Crea JPEG de prueba con EXIF (orientación, fecha y GPS) sin depender de ninguna librería. */
class ExifJpeg
{
    /**
     * @param  array{orientation?: int, date?: string, lat?: array{0:int,1:int,2:int,3:string}, lng?: array{0:int,1:int,2:int,3:string}}  $exif
     */
    public static function make(int $width, int $height, array $exif): string
    {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 80, 40));
        // Una marca en la esquina superior izquierda para comprobar cómo queda girada.
        imagefilledrectangle($img, 0, 0, 20, 20, imagecolorallocate($img, 0, 0, 255));
        ob_start();
        imagejpeg($img, null, 90);
        $jpeg = (string) ob_get_clean();

        $tiff = self::tiff($exif);
        $app1 = "Exif\0\0".$tiff;

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
    }

    private static function tiff(array $exif): string
    {
        $le = fn (int $v, int $bytes = 4) => $bytes === 2 ? pack('v', $v) : pack('V', $v);
        $entry = fn (int $tag, int $type, int $count, string $value) => pack('v', $tag).pack('v', $type).pack('V', $count).$value;

        // IFD0: orientación + punteros a Exif y GPS. Tamaño fijo = 2 + 3*12 + 4 = 42 -> termina en 50.
        $exifIfdOffset = 50;
        $dateData = ($exif['date'] ?? '2026:10:20 14:30:00')."\0";          // 20 bytes
        $dateOffset = $exifIfdOffset + 18;
        $exifIfd = $le(1, 2).$entry(0x9003, 2, 20, $le($dateOffset)).$le(0);

        $gpsOffset = $dateOffset + 20;                                          // 88
        $latOffset = $gpsOffset + 54;                                           // 142
        $lngOffset = $latOffset + 24;                                           // 166
        $lat = $exif['lat'] ?? [18, 28, 30, 'N'];
        $lng = $exif['lng'] ?? [69, 53, 24, 'W'];
        $rationals = fn (array $dms) => pack('VVVVVV', $dms[0], 1, $dms[1], 1, $dms[2], 1);
        $gpsIfd = $le(4, 2)
            .$entry(0x0001, 2, 2, $lat[3]."\0\0\0")
            .$entry(0x0002, 5, 3, $le($latOffset))
            .$entry(0x0003, 2, 2, $lng[3]."\0\0\0")
            .$entry(0x0004, 5, 3, $le($lngOffset))
            .$le(0);

        $ifd0 = $le(3, 2)
            .$entry(0x0112, 3, 1, $le($exif['orientation'] ?? 1, 2)."\0\0")
            .$entry(0x8769, 4, 1, $le($exifIfdOffset))
            .$entry(0x8825, 4, 1, $le($gpsOffset))
            .$le(0);

        return 'II'.pack('v', 42).pack('V', 8).$ifd0.$exifIfd.$dateData.$gpsIfd.$rationals($lat).$rationals($lng);
    }
}
