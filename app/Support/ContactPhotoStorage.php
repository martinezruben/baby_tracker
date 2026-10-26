<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Foto de un contacto: se vuelve a codificar como JPEG cuadrado de 480 px (recorte centrado), girada según su
 * orientación y sin EXIF (ubicación incluida), en el disco privado. Solo se sirve por `contacts.photo`.
 */
class ContactPhotoStorage
{
    public const SIDE = 480;

    private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public function __construct(private PhotoStorage $photos) {}

    /** Guarda la foto y devuelve su ruta en el disco privado; no toca la base de datos. */
    public function store(UploadedFile $file, int $familyId): string
    {
        @ini_set('memory_limit', '512M');

        $path = $file->getRealPath();
        $info = @getimagesize($path);
        if (! $info || ! in_array($info[2], self::TYPES, true) || $info[0] * $info[1] > PhotoStorage::MAX_PIXELS) {
            throw ValidationException::withMessages(['photo' => trans('ui.contactos.photo_invalid')]);
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['photo' => trans('ui.contactos.photo_invalid')]);
        }

        $orientation = 1;
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        }

        $image = $this->square($this->photos->orient($source, $orientation));

        $stored = "contacts/{$familyId}/".Str::uuid().'.jpg';
        Storage::disk('local')->put($stored, $this->photos->encode($image, 'image/jpeg'));

        return $stored;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    /** Recorte cuadrado centrado, de SIDE × SIDE, sobre fondo blanco (por si el PNG tenía transparencia). */
    private function square(GdImage $image): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $side = min($w, $h);

        $out = imagecreatetruecolor(self::SIDE, self::SIDE);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $image, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), self::SIDE, self::SIDE, $side, $side);

        return $out;
    }
}
