<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Support\PhotoStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('photos:thumbnails')]
#[Description('Vuelve a crear las miniaturas pesadas de la galería (320 px, calidad 70)')]
class RebuildPhotoThumbnails extends Command
{
    public function handle(PhotoStorage $storage): int
    {
        $done = 0;
        $total = 0;

        Photo::query()->chunkById(50, function ($photos) use ($storage, &$done, &$total) {
            foreach ($photos as $photo) {
                $total++;
                $done += $storage->rebuildThumbnail($photo) ? 1 : 0;
            }
        });

        $this->info("Miniaturas: {$done} regeneradas de {$total} fotos.");

        return self::SUCCESS;
    }
}
