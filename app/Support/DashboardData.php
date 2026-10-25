<?php

namespace App\Support;

use App\Enums\NotificationEventType;
use App\Models\ActiveBreastFeed;
use App\Models\Baby;
use App\Models\DiaperLog;
use App\Models\FeedLog;
use App\Models\MedicineLog;
use App\Models\NotificationRule;
use App\Models\PlayLog;
use App\Models\SleepLog;

class DashboardData
{
    public function __construct(private FamilyClock $clock, private BreastFeedSession $breast) {}

    /**
     * Conteos y métricas de un rango (hoy, semana o mes) para el resumen con la dona.
     *
     * @return array<string, mixed>
     */
    public function summary(Baby $baby, string $range): array
    {
        [$from, $to] = $this->clock->range($range);

        $feeds = FeedLog::where('baby_id', $baby->id)->whereBetween('occurred_at', [$from, $to->subSecond()]);
        $sleeps = SleepLog::where('baby_id', $baby->id)->whereBetween('started_at', [$from, $to->subSecond()]);
        $diapers = DiaperLog::where('baby_id', $baby->id)->whereBetween('occurred_at', [$from, $to->subSecond()])->count();
        $medicines = MedicineLog::where('baby_id', $baby->id)->whereBetween('occurred_at', [$from, $to->subSecond()])->count();
        $plays = PlayLog::where('baby_id', $baby->id)->whereBetween('started_at', [$from, $to->subSecond()])->count();

        $feedCount = (clone $feeds)->count();
        $sleepCount = (clone $sleeps)->count();

        $segments = collect([
            ['key' => 'feed', 'count' => $feedCount],
            ['key' => 'sleep', 'count' => $sleepCount],
            ['key' => 'diaper', 'count' => $diapers],
            ['key' => 'medicine', 'count' => $medicines],
            ['key' => 'play', 'count' => $plays],
        ]);

        return [
            'range' => $range,
            'total' => $segments->sum('count'),
            'segments' => $segments->values(),
            'feed' => ['count' => $feedCount, 'ml' => (int) round((clone $feeds)->where('unit', 'ml')->sum('amount'))],
            'sleep' => ['count' => $sleepCount, 'minutes' => (int) (clone $sleeps)->sum('duration_minutes')],
            'diaper' => $diapers,
            'medicine' => $medicines,
            'play' => $plays,
        ];
    }

    /** Sesiones en curso: lactancia y sueño abierto. */
    public function sessions(Baby $baby): array
    {
        $breast = ActiveBreastFeed::where('baby_id', $baby->id)->first();
        $sleep = SleepLog::where('baby_id', $baby->id)->whereNull('ended_at')->latest('started_at')->first();

        return [
            'breast' => $breast ? $this->breast->present($breast) : null,
            'sleep' => $sleep ? ['id' => $sleep->id, 'started_at' => $sleep->started_at->toIso8601String()] : null,
        ];
    }

    /**
     * Próximos recordatorios activos (los de citas, vacunas y medicinas se sumarán aquí).
     *
     * @return array<int, array{id: int, kind: string, title: string, at: string}>
     */
    public function upcoming(Baby $baby, int $limit = 6): array
    {
        return NotificationRule::query()
            ->where('family_id', $baby->family_id)
            ->where('event_type', NotificationEventType::Custom)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('baby_id')->orWhere('baby_id', $baby->id))
            ->where('event_at', '>', now())
            ->orderBy('event_at')
            ->limit($limit)
            ->get()
            ->map(fn (NotificationRule $rule) => [
                'id' => $rule->id,
                'kind' => 'reminder',
                'title' => $rule->title,
                'at' => $rule->event_at->toIso8601String(),
            ])
            ->all();
    }
}
