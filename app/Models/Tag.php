<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $guarded = ['id'];

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class);
    }

    /** Etiquetas sin mayúsculas ni espacios de más; null si queda vacía. */
    public static function normalize(string $name): ?string
    {
        $clean = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
        $clean = mb_substr($clean, 0, 40);

        return $clean === '' ? null : $clean;
    }
}
