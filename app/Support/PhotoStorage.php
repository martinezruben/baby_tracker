<?php

namespace App\Support;

use App\Models\Baby;
use App\Models\Caretaker;
use App\Models\Photo;
use Carbon\CarbonImmutable;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guarda fotos en el disco privado. La copia guardada se vuelve a codificar: sale ya girada según su
 * orientación, a un tamaño razonable y SIN metadatos (el GPS de la cámara no se queda dentro del
 * archivo); la fecha y la ubicación EXIF pasan a columnas que la persona puede editar o borrar.
 */
class PhotoStorage
{
    public const MAX_SIDE = 2048;

    public const THUMB_SIDE = 480;

    public const MAX_PIXELS = 50_000_000;

    private const TYPES = [IMAGETYPE_JPEG => ['image/jpeg', 'jpg'], IMAGETYPE_PNG => ['image/png', 'png'], IMAGETYPE_WEBP => ['image/webp', 'webp']];

    /** @param  array<string, mixed>  $attributes  columnas extra (p. ej. life_event_id) */
    public function store(UploadedFile $file, Baby $baby, Caretaker $by, array $attributes = []): Photo
    {
        // Decodificar una foto de 12 MP necesita bastante más memoria que el límite por defecto.
        @ini_set('memory_limit', '512M');

        $path = $file->getRealPath();
        $info = @getimagesize($path);

        if (! $info || ! isset(self::TYPES[$info[2]])) {
            $this->fail('invalid');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            $this->fail('too_big');
        }

        [$mime, $ext] = self::TYPES[$info[2]];
        $source = @imagecreatefromstring((string) file_get_contents($path));
        if (! $source instanceof GdImage) {
            $this->fail('invalid');
        }

        $meta = $info[2] === IMAGETYPE_JPEG ? $this->readExif($path, $baby) : [];
        $image = $this->orient($source, $meta['orientation'] ?? 1);
        $image = $this->fit($image, self::MAX_SIDE);
        $thumb = $this->fit($image, self::THUMB_SIDE, flatten: true);

        $original = $this->encode($image, $mime);
        $thumbnail = $this->encode($thumb, 'image/jpeg');
        $size = strlen($original) + strlen($thumbnail);

        $this->ensureQuota($baby, $size);

        $name = (string) Str::uuid();
        $dir = "photos/{$baby->family_id}";
        $storedPath = "{$dir}/{$name}.{$ext}";
        $thumbPath = "{$dir}/{$name}_thumb.jpg";
        Storage::disk('local')->put($storedPath, $original);
        Storage::disk('local')->put($thumbPath, $thumbnail);

        // La fecha de la propia foto manda; si no la trae, la que indique quien llama (p. ej. el día del momento).
        $takenAt = $meta['taken_at'] ?? $attributes['taken_at'] ?? now();
        unset($attributes['taken_at']);

        return Photo::create($attributes + [
            'family_id' => $baby->family_id,
            'baby_id' => $baby->id,
            'uploaded_by' => $by->id,
            'path' => $storedPath,
            'thumb_path' => $thumbPath,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'mime' => $mime,
            'size' => $size,
            'width' => imagesx($image),
            'height' => imagesy($image),
            'taken_at' => $takenAt,
            'latitude' => $meta['latitude'] ?? null,
            'longitude' => $meta['longitude'] ?? null,
        ]);
    }

    /** Bytes que ocupan las fotos de una familia. */
    public function usage(int $familyId): int
    {
        return (int) Photo::where('family_id', $familyId)->sum('size');
    }

    private function ensureQuota(Baby $baby, int $incoming): void
    {
        $quota = ($baby->family->settings->photo_quota_mb ?? 2048) * 1024 * 1024;

        if ($this->usage($baby->family_id) + $incoming > $quota) {
            $this->fail('quota');
        }
    }

    /** @return array{orientation?: int, taken_at?: CarbonImmutable, latitude?: float, longitude?: float} */
    private function readExif(string $path, Baby $baby): array
    {
        if (! function_exists('exif_read_data')) {
            return [];
        }

        $exif = @exif_read_data($path, 'ANY_TAG', true);
        if (! is_array($exif)) {
            return [];
        }

        $meta = ['orientation' => (int) ($exif['IFD0']['Orientation'] ?? 1)];

        $date = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? null;
        if (is_string($date) && preg_match('/^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}/', $date)) {
            try {
                // El EXIF no trae zona horaria: se toma como hora de la familia.
                $meta['taken_at'] = CarbonImmutable::createFromFormat('Y:m:d H:i:s', substr($date, 0, 19), FamilyClock::for($baby->family)->timezone)->utc();
            } catch (\Throwable) {
            }
        }

        $gps = $exif['GPS'] ?? [];
        if (isset($gps['GPSLatitude'], $gps['GPSLatitudeRef'], $gps['GPSLongitude'], $gps['GPSLongitudeRef'])) {
            $lat = $this->degrees($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
            $lng = $this->degrees($gps['GPSLongitude'], $gps['GPSLongitudeRef']);

            if ($lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180) {
                $meta['latitude'] = round($lat, 6);
                $meta['longitude'] = round($lng, 6);
            }
        }

        return $meta;
    }

    /** @param  array<int, string>  $parts  grados, minutos y segundos como fracciones "18/1" */
    private function degrees(array $parts, string $ref): ?float
    {
        if (count($parts) < 3) {
            return null;
        }

        $values = array_map(function ($fraction) {
            [$n, $d] = array_pad(explode('/', (string) $fraction), 2, 1);

            return (float) $d == 0.0 ? 0.0 : (float) $n / (float) $d;
        }, $parts);

        $degrees = $values[0] + $values[1] / 60 + $values[2] / 3600;

        return in_array(strtoupper($ref), ['S', 'W'], true) ? -$degrees : $degrees;
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        $rotated = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            6, 5 => imagerotate($image, 270, 0), // 90° en sentido horario
            8, 7 => imagerotate($image, 90, 0),
            default => $image,
        };
        $rotated = $rotated ?: $image;

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
        }

        return $rotated;
    }

    private function fit(GdImage $image, int $max, bool $flatten = false): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor($nw, $nh);

        if ($flatten) {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255)); // sin transparencia en miniaturas JPEG
        } else {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $canvas;
    }

    private function encode(GdImage $image, string $mime): string
    {
        ob_start();
        match ($mime) {
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, 85),
            default => imagejpeg($image, null, 85),
        };

        return (string) ob_get_clean();
    }

    private function fail(string $reason): never
    {
        throw ValidationException::withMessages(['photos' => trans("ui.fotos.errors.{$reason}")]);
    }
}
