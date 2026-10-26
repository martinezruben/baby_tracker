<?php

namespace App\Support;

/** Conversión de volúmenes de leche entre ml y onzas líquidas (oz) de EE. UU. */
class Units
{
    public const ML_PER_OZ = 29.5735295625;

    public static function volume(float $value, ?string $from, string $to): float
    {
        if ($from === $to || ! in_array($from, ['ml', 'oz'], true)) {
            return $value;
        }

        return $to === 'oz' ? $value / self::ML_PER_OZ : $value * self::ML_PER_OZ;
    }

    /** Redondeo para mostrar: ml enteros, oz con un decimal. */
    public static function round(float $value, string $unit): float
    {
        return $unit === 'oz' ? round($value, 1) : (float) round($value);
    }

    /** Convierte y redondea un volumen a la unidad de la familia. */
    public static function display(float $value, ?string $from, string $to): float
    {
        return self::round(self::volume($value, $from, $to), $to);
    }
}
