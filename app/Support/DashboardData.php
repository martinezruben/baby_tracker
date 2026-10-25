<?php

namespace App\Support;

use App\Enums\AppointmentStatus;
use App\Enums\DoseStatus;
use App\Enums\NotificationEventType;
use App\Enums\PrescriptionStatus;
use App\Models\ActiveBreastFeed;
use App\Models\Appointment;
use App\Models\Baby;
use App\Models\DiaperLog;
use App\Models\FeedLog;
use App\Models\MedicineLog;
use App\Models\NotificationRule;
use App\Models\PlayLog;
use App\Models\Prescription;
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
     * Próximos eventos del bebé: recordatorios, citas médicas y la siguiente toma de cada tratamiento.
     *
     * @return array<int, array{id: int, kind: string, title: string, at: string, href: string}>
     */
    public function upcoming(Baby $baby, int $limit = 6): array
    {
        $reminders = NotificationRule::query()
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
                'href' => "/recordatorios/{$rule->id}/editar",
            ]);

        $appointments = Appointment::query()
            ->where('baby_id', $baby->id)
            ->where('status', AppointmentStatus::Scheduled)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get()
            ->map(fn (Appointment $a) => [
                'id' => $a->id,
                'kind' => 'appointment',
                'title' => $a->reason,
                'at' => $a->scheduled_at->toIso8601String(),
                'href' => "/citas/{$a->id}",
            ]);

        $doses = Prescription::query()
            ->where('baby_id', $baby->id)
            ->where('status', PrescriptionStatus::Active)
            ->with(['medicine:id,name', 'doses' => fn ($q) => $q->where('status', DoseStatus::Pending)->orderBy('scheduled_at')->limit(1)])
            ->get()
            ->filter(fn (Prescription $p) => $p->doses->isNotEmpty())
            ->map(fn (Prescription $p) => [
                'id' => $p->id,
                'kind' => 'dose',
                'title' => $p->medicine->name,
                'at' => $p->doses->first()->scheduled_at->toIso8601String(),
                'href' => $p->appointment_id ? "/citas/{$p->appointment_id}" : '/citas',
            ]);

        // Vacunas: la primera dosis vencida (si la hay) y la siguiente por aplicar.
        $vaccines = VaccineSchedule::for($baby)->rows($baby)->whereIn('status', ['overdue', 'soon', 'later']);
        $nextVaccines = collect([$vaccines->firstWhere('status', 'overdue'), $vaccines->whereIn('status', ['soon', 'later'])->first()])
            ->filter()
            ->map(fn (array $r) => [
                'id' => $r['dose']->id,
                'kind' => 'vaccine',
                'title' => trim($r['dose']->vaccine_name.' '.$r['dose']->dose_label),
                'at' => $r['due_on']->setTime(9, 0)->utc()->toIso8601String(),
                'href' => '/vacunas',
                'overdue' => $r['status'] === 'overdue',
            ]);

        return $reminders->concat($appointments)->concat($doses)->concat($nextVaccines)->sortBy('at')->take($limit)->values()->all();
    }
}
