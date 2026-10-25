<?php

namespace App\Support;

/** Textos de interfaz para el frontend: el idioma pedido sobre el de respaldo (es). */
class UiTranslations
{
    public static function for(string $locale): array
    {
        $fallback = self::load('es');

        return $locale === 'es'
            ? $fallback
            : array_replace_recursive($fallback, self::load($locale));
    }

    private static function load(string $locale): array
    {
        $path = lang_path("{$locale}/ui.php");

        return is_file($path) ? require $path : [];
    }
}
