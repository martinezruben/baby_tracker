<?php

namespace App\Support;

/**
 * Lee el esquema pegado desde la cartilla, una dosis por línea: "vacuna; dosis; edad; notas".
 * Separa por tabulador, punto y coma, barra vertical o coma. La edad admite "0", "al nacer",
 * "2 meses", "6 semanas", "10 días" o "5 años"; sin unidad se entiende en meses.
 */
class VaccineScheduleImport
{
    public const MAX_LINES = 100;

    /**
     * @return array{rows: array<int, array{vaccine_name: string, dose_label: ?string, age_days: int, notes: ?string}>, errors: array<int, array{line: int, reason: string}>}
     */
    public static function parse(string $text): array
    {
        $rows = [];
        $errors = [];
        $lines = preg_split('/\R/u', trim($text)) ?: [];

        foreach ($lines as $i => $raw) {
            $line = trim($raw);
            $number = $i + 1;

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (count($rows) + count($errors) >= self::MAX_LINES) {
                $errors[] = ['line' => $number, 'reason' => 'too_many'];
                break;
            }

            $cells = array_map('trim', str_getcsv($line, self::delimiter($line)));
            $cells = array_pad($cells, 4, '');

            if ($cells[0] === '' || mb_strlen($cells[0]) > 80) {
                $errors[] = ['line' => $number, 'reason' => 'name'];

                continue;
            }

            $days = self::ageDays($cells[2]);
            if ($days === null) {
                $errors[] = ['line' => $number, 'reason' => 'age'];

                continue;
            }

            $rows[] = [
                'vaccine_name' => $cells[0],
                'dose_label' => $cells[1] !== '' ? mb_substr($cells[1], 0, 40) : null,
                'age_days' => $days,
                'notes' => $cells[3] !== '' ? mb_substr($cells[3], 0, 200) : null,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** Edad en días a partir de un texto; null si no se entiende. */
    public static function ageDays(string $text): ?int
    {
        $text = mb_strtolower(trim($text));

        if (in_array($text, ['nacer', 'al nacer', 'nacimiento', 'recién nacido', 'recien nacido'], true)) {
            return 0;
        }

        if (! preg_match('/^(\d+(?:[.,]\d+)?)\s*(d|día|dia|días|dias|s|sem|semana|semanas|m|mes|meses|a|año|ano|años|anos)?$/u', $text, $m)) {
            return null;
        }

        $n = (float) str_replace(',', '.', $m[1]);
        $days = match ($m[2] ?? '') {
            'd', 'día', 'dia', 'días', 'dias' => $n,
            's', 'sem', 'semana', 'semanas' => $n * 7,
            'a', 'año', 'ano', 'años', 'anos' => $n * 365.25,
            default => $n * 30.4375, // meses
        };

        $days = (int) round($days);

        return $days > 9000 ? null : $days;
    }

    private static function delimiter(string $line): string
    {
        return match (true) {
            str_contains($line, "\t") => "\t",
            str_contains($line, ';') => ';',
            str_contains($line, '|') => '|',
            default => ',',
        };
    }
}
