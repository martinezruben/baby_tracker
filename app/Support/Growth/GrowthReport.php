<?php

namespace App\Support\Growth;

use App\Enums\MeasurementType;
use App\Models\Baby;
use App\Models\Measurement;
use Carbon\CarbonImmutable;

/** Datos de la pantalla Crecimiento: curvas de referencia y medidas del bebé con su percentil. */
class GrowthReport
{
    private const TYPES = ['weight' => MeasurementType::Weight, 'height' => MeasurementType::Height, 'head' => MeasurementType::HeadCircumference];

    public function __construct(private string $standard, private string $weightUnit, private string $lengthUnit) {}

    /** @return array<string, mixed> */
    public function build(Baby $baby, string $measure): array
    {
        $sex = $baby->gender?->value;
        $unit = $measure === 'weight' ? $this->weightUnit : $this->lengthUnit;
        $base = ['measure' => $measure, 'unit' => $unit, 'sex_known' => $sex !== null];

        $birth = CarbonImmutable::parse($baby->birth_date)->startOfDay();
        $ageDays = max(0, (int) $birth->diffInDays(now()->startOfDay()));

        // El CDC solo cubre hasta los 36 meses: después se usa la OMS.
        $standard = $this->standard === 'cdc' && $ageDays <= GrowthStandards::maxDays('cdc') ? 'cdc' : 'who';

        $rows = Measurement::where('baby_id', $baby->id)->where('type', self::TYPES[$measure])->orderBy('measured_on')->orderBy('id')->get();

        $points = $rows->map(function (Measurement $m) use ($birth, $standard, $measure, $sex, $unit) {
            $day = (int) $birth->diffInDays(CarbonImmutable::parse($m->measured_on)->startOfDay(), false);
            $metric = $this->toMetric((float) $m->value, $m->unit);
            $z = $sex && $day >= 0 ? GrowthStandards::zScore($standard, $measure, $sex, $day, $metric) : null;

            return [
                'id' => $m->id,
                'day' => $day,
                'date' => CarbonImmutable::parse($m->measured_on)->toDateString(),
                'value' => round($this->fromMetric($metric, $unit), 2),
                'percentile' => $z === null ? null : round(GrowthStandards::percentile($z), 1),
                'z' => $z === null ? null : round($z, 2),
            ];
        })->values();

        if (! $sex) {
            return $base + ['standard' => $standard, 'points' => $points, 'bands' => null, 'x_max' => 180, 'age_days' => $ageDays];
        }

        // Ventana de edades: hasta unos 2 meses después de la edad actual (mínimo 6 meses), a mes completo.
        $last = max($ageDays, (int) ($points->max('day') ?? 0));
        $xMax = (int) min(GrowthStandards::maxDays($standard), max(183, ceil(($last + 60) / 30.4375) * 30.4375));

        $bands = collect(GrowthStandards::bands($standard, $measure, $sex, 0, $xMax))
            ->map(fn (array $curve) => array_map(fn (array $p) => [$p[0], round($this->fromMetric($p[1], $unit), 2)], $curve))
            ->all();

        return $base + ['standard' => $standard, 'points' => $points, 'bands' => $bands, 'x_max' => $xMax, 'age_days' => $ageDays];
    }

    private function toMetric(float $value, ?string $unit): float
    {
        return match ($unit) {
            'lb' => $value * 0.45359237,
            'in' => $value * 2.54,
            default => $value,
        };
    }

    private function fromMetric(float $value, string $unit): float
    {
        return match ($unit) {
            'lb' => $value / 0.45359237,
            'in' => $value / 2.54,
            default => $value,
        };
    }
}
