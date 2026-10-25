<?php

namespace App\Support\Growth;

/**
 * Curvas de crecimiento de la OMS (0-5 años) y del CDC (0-36 meses) a partir de sus tablas LMS
 * oficiales (`resources/data/growth.json`; son datos, no se inventan ni se editan a mano).
 * Percentil de una medida: z = ((x/M)^L − 1) / (L·S); valor de un percentil: x = M·(1 + L·S·z)^(1/L).
 */
class GrowthStandards
{
    public const MEASURES = ['weight', 'height', 'head'];

    /** Percentiles que se dibujan y su puntuación z. */
    public const BANDS = [3 => -1.8807936, 15 => -1.0364334, 50 => 0.0, 85 => 1.0364334, 97 => 1.8807936];

    /** Edad máxima (días) que cubre cada estándar. */
    private const MAX_DAYS = ['who' => 1830, 'cdc' => 1080];

    private static ?array $data = null;

    public static function supports(string $standard, string $measure, string $sex): bool
    {
        return isset(self::data()[$standard][$measure][$sex]);
    }

    /** Edad máxima cubierta por el estándar, en días. */
    public static function maxDays(string $standard): int
    {
        return self::MAX_DAYS[$standard] ?? 0;
    }

    /** @return array{0: float, 1: float, 2: float}|null  [L, M, S] interpolado a esa edad; null fuera de rango. */
    public static function lms(string $standard, string $measure, string $sex, float $ageDays): ?array
    {
        $rows = self::data()[$standard][$measure][$sex] ?? [];
        if (! $rows || $ageDays < $rows[0][0] || $ageDays > $rows[count($rows) - 1][0]) {
            return null;
        }

        foreach ($rows as $i => $row) {
            if ($row[0] >= $ageDays) {
                $prev = $rows[max(0, $i - 1)];
                $span = $row[0] - $prev[0];
                $f = $span > 0 ? ($ageDays - $prev[0]) / $span : 0.0;

                return [$prev[1] + ($row[1] - $prev[1]) * $f, $prev[2] + ($row[2] - $prev[2]) * $f, $prev[3] + ($row[3] - $prev[3]) * $f];
            }
        }

        return null;
    }

    /** Puntuación z de una medida (kg o cm) o null si la edad está fuera de las tablas. */
    public static function zScore(string $standard, string $measure, string $sex, float $ageDays, float $value): ?float
    {
        $lms = self::lms($standard, $measure, $sex, $ageDays);
        if (! $lms || $value <= 0) {
            return null;
        }
        [$l, $m, $s] = $lms;

        return abs($l) < 1e-9 ? log($value / $m) / $s : (($value / $m) ** $l - 1) / ($l * $s);
    }

    /** Percentil (0-100) de una puntuación z. */
    public static function percentile(float $z): float
    {
        return 100 * 0.5 * self::erfc(-$z / M_SQRT2);
    }

    public static function valueAt(array $lms, float $z): float
    {
        [$l, $m, $s] = $lms;

        return abs($l) < 1e-9 ? $m * exp($s * $z) : $m * (1 + $l * $s * $z) ** (1 / $l);
    }

    /**
     * Curvas de los percentiles entre dos edades, en las edades de la tabla.
     *
     * @return array<int, array<int, array{0: float, 1: float}>> percentil => [[día, valor]...]
     */
    public static function bands(string $standard, string $measure, string $sex, int $fromDay, int $toDay): array
    {
        $rows = self::data()[$standard][$measure][$sex] ?? [];
        $bands = array_fill_keys(array_keys(self::BANDS), []);

        foreach ($rows as $row) {
            if ($row[0] < $fromDay || $row[0] > $toDay) {
                continue;
            }
            foreach (self::BANDS as $p => $z) {
                $bands[$p][] = [(float) $row[0], round(self::valueAt([$row[1], $row[2], $row[3]], $z), 3)];
            }
        }

        return $bands;
    }

    /** Complemento de la función de error (Numerical Recipes, error < 1,2e-7). */
    private static function erfc(float $x): float
    {
        $z = abs($x);
        $t = 1 / (1 + 0.5 * $z);
        $r = $t * exp(-$z * $z - 1.26551223 + $t * (1.00002368 + $t * (0.37409196 + $t * (0.09678418 + $t * (-0.18628806 + $t * (0.27886807 + $t * (-1.13520398 + $t * (1.48851587 + $t * (-0.82215223 + $t * 0.17087277)))))))));

        return $x >= 0 ? $r : 2 - $r;
    }

    private static function data(): array
    {
        return self::$data ??= json_decode(file_get_contents(resource_path('data/growth.json')), true, 512, JSON_THROW_ON_ERROR);
    }
}
