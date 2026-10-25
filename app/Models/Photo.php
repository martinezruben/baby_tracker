<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_favorite' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Al borrar la foto se borran sus archivos del disco privado.
        static::deleted(function (Photo $photo) {
            Storage::disk('local')->delete([$photo->path, $photo->thumb_path]);
        });
    }

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(LifeEvent::class, 'life_event_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Caretaker::class, 'uploaded_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null || $this->place_name !== null;
    }
}
