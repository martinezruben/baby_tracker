<?php

namespace App\Support;

use App\Models\Family;
use Illuminate\Support\Facades\Cache;

/** ¿Ya hay una familia? Se consulta en cada petición, así que el "sí" se recuerda un rato. */
class Installation
{
    private const KEY = 'kidu.installed';

    public static function hasFamily(): bool
    {
        if (Cache::get(self::KEY)) {
            return true;
        }

        $has = Family::query()->exists();

        if ($has) {
            Cache::put(self::KEY, true, 3600);
        }

        return $has;
    }

    /** Tras vaciar la instalación (restaurar con --wipe), para que se vuelva a mirar. */
    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
