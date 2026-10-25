<?php

namespace App\Support;

use App\Enums\DiaperType;
use App\Enums\FeedType;
use App\Models\Baby;
use App\Models\DiaperLog;
use App\Models\FeedLog;
use App\Models\MedicineLog;
use App\Models\SleepLog;
use Carbon\CarbonImmutable;

/** Resumen por día (hora de la familia) de los últimos N días para la pantalla Reportes. */
class ReportData
{
    public const RANGES = [7, 14, 30];

    public function __construct(private FamilyClock $clock) {}

    /** @return array<string, mixed> */
    public function build(Baby $baby, int $days): array
    {
        $today = $this->clock->now()->startOfDay();
        $first = $today->subDays($days - 1);
        $from = $first->utc();
        $to = $today->addDay()->utc();

        $rows = [];
        for ($i = 0; $i < $days; $i++) {
            $rows[$first->addDays($i)->toDateString()] = ['date' => $first->addDays($i)->toDateString(), 'feeds' => 0, 'ml' => 0, 'breast_minutes' => 0, 'sleep_minutes' => 0, 'diapers' => 0, 'wet' => 0, 'dirty' => 0, 'medicines' => 0];
        }
        $day = fn ($at) => CarbonImmutable::instance($at)->setTimezone($this->clock->timezone)->toDateString();

        FeedLog::where('baby_id', $baby->id)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->get()
            ->each(function (FeedLog $f) use (&$rows, $day) {
                $k = $day($f->occurred_at);
                $rows[$k]['feeds']++;
                if ($f->type === FeedType::Bottle && $f->unit === 'ml') {
                    $rows[$k]['ml'] += (float) $f->amount;
                }
                if ($f->type === FeedType::Breast) {
                    $rows[$k]['breast_minutes'] += (int) round(($f->duration_seconds ?? 0) / 60);
                }
            });

        SleepLog::where('baby_id', $baby->id)->where('started_at', '>=', $from)->where('started_at', '<', $to)->whereNotNull('ended_at')->get()
            ->each(function (SleepLog $s) use (&$rows, $day) {
                $rows[$day($s->started_at)]['sleep_minutes'] += (int) $s->duration_minutes;
            });

        DiaperLog::where('baby_id', $baby->id)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->get()
            ->each(function (DiaperLog $d) use (&$rows, $day) {
                $k = $day($d->occurred_at);
                $rows[$k]['diapers']++;
                if (in_array($d->type, [DiaperType::Wet, DiaperType::Both], true)) {
                    $rows[$k]['wet']++;
                }
                if (in_array($d->type, [DiaperType::Dirty, DiaperType::Both], true)) {
                    $rows[$k]['dirty']++;
                }
            });

        MedicineLog::where('baby_id', $baby->id)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->pluck('occurred_at')
            ->each(function ($at) use (&$rows, $day) {
                $rows[$day($at)]['medicines']++;
            });

        $series = array_values($rows);
        // Promedios solo con los días que tienen algún registro, para no diluirlos con días sin usar la app.
        $active = collect($series)->filter(fn ($r) => $r['feeds'] + $r['diapers'] + $r['sleep_minutes'] + $r['medicines'] > 0);
        $avg = fn (string $key) => $active->isEmpty() ? 0 : round($active->avg($key), 1);

        return [
            'days' => $days,
            'series' => $series,
            'active_days' => $active->count(),
            'averages' => [
                'feeds' => $avg('feeds'),
                'ml' => (int) round($active->avg('ml') ?? 0),
                'sleep_hours' => round(($active->avg('sleep_minutes') ?? 0) / 60, 1),
                'diapers' => $avg('diapers'),
            ],
        ];
    }
}
