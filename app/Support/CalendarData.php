<?php

namespace App\Support;

use App\Enums\AppointmentStatus;
use App\Enums\NotificationEventType;
use App\Models\Appointment;
use App\Models\Baby;
use App\Models\DiaperLog;
use App\Models\FeedLog;
use App\Models\LifeEvent;
use App\Models\MedicineLog;
use App\Models\NotificationRule;
use App\Models\PrescriptionDose;
use App\Models\SleepLog;
use App\Models\Vaccination;
use Carbon\CarbonImmutable;

/**
 * Todo lo que ocurre (o está por ocurrir) en un mes para un bebé, agrupado por día de la familia:
 * citas, vacunas, tomas de medicina, recordatorios, momentos y cuántos registros hubo de cada actividad.
 */
class CalendarData
{
    public function __construct(private FamilyClock $clock) {}

    /** @return array{month: string, days: array<string, array{items: array<int, array<string, mixed>>, counts: array<string, int>}>} */
    public function month(Baby $baby, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i:s', "{$month}-01 00:00:00", $this->clock->timezone);
        $end = $start->addMonth();
        [$from, $to] = [$start->utc(), $end->utc()];

        $days = [];
        $add = function (string $kind, array $item, CarbonImmutable|string $when) use (&$days) {
            $date = is_string($when) ? $when : $when->setTimezone($this->clock->timezone)->toDateString();
            $days[$date]['items'][] = ['kind' => $kind] + $item;
        };
        $count = function (string $type, $at) use (&$days) {
            $date = CarbonImmutable::instance($at)->setTimezone($this->clock->timezone)->toDateString();
            $days[$date]['counts'][$type] = ($days[$date]['counts'][$type] ?? 0) + 1;
        };

        Appointment::where('baby_id', $baby->id)->where('status', '!=', AppointmentStatus::Cancelled)
            ->where('scheduled_at', '>=', $from)->where('scheduled_at', '<', $to)->get()
            ->each(fn ($a) => $add('appointment', [
                'id' => $a->id, 'title' => $a->reason, 'at' => $a->scheduled_at->toIso8601String(), 'href' => "/citas/{$a->id}",
                'state' => $a->status === AppointmentStatus::Completed ? 'done' : 'pending',
            ], CarbonImmutable::instance($a->scheduled_at)));

        foreach (VaccineSchedule::for($baby)->rows($baby) as $row) {
            if ($row['status'] !== 'applied' && $row['due_on']->greaterThanOrEqualTo($start) && $row['due_on']->lessThan($end)) {
                $add('vaccine', [
                    'id' => $row['dose']->id, 'title' => trim($row['dose']->vaccine_name.' '.$row['dose']->dose_label), 'at' => null, 'href' => '/vacunas',
                    'state' => $row['status'] === 'overdue' ? 'overdue' : 'pending',
                ], $row['due_on']->toDateString());
            }
        }
        Vaccination::where('baby_id', $baby->id)->where('administered_at', '>=', $from)->where('administered_at', '<', $to)->get()
            ->each(fn ($v) => $add('vaccine', [
                'id' => $v->id, 'title' => trim($v->vaccine_name.' '.$v->dose_label), 'at' => $v->administered_at->toIso8601String(), 'href' => '/vacunas', 'state' => 'done',
            ], CarbonImmutable::instance($v->administered_at)));

        PrescriptionDose::query()
            ->whereHas('prescription', fn ($q) => $q->where('baby_id', $baby->id))
            ->with('prescription.medicine:id,name')
            ->where('scheduled_at', '>=', $from)->where('scheduled_at', '<', $to)->get()
            ->each(fn ($d) => $add('dose', [
                'id' => $d->id, 'title' => $d->prescription->medicine->name, 'at' => $d->scheduled_at->toIso8601String(),
                'href' => $d->prescription->appointment_id ? "/citas/{$d->prescription->appointment_id}" : '/citas',
                'state' => $d->status->value === 'given' ? 'done' : ($d->status->value === 'skipped' ? 'skipped' : 'pending'),
            ], CarbonImmutable::instance($d->scheduled_at)));

        NotificationRule::where('family_id', $baby->family_id)->where('event_type', NotificationEventType::Custom)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('baby_id')->orWhere('baby_id', $baby->id))
            ->where('event_at', '>=', $from)->where('event_at', '<', $to)->get()
            ->each(fn ($r) => $add('reminder', [
                'id' => $r->id, 'title' => $r->title, 'at' => $r->event_at->toIso8601String(), 'href' => "/recordatorios/{$r->id}/editar", 'state' => 'pending',
            ], CarbonImmutable::instance($r->event_at)));

        LifeEvent::where('baby_id', $baby->id)->whereDate('occurred_on', '>=', $start->toDateString())->whereDate('occurred_on', '<', $end->toDateString())->get()
            ->each(fn ($e) => $add('life_event', [
                'id' => $e->id, 'title' => $e->title ?: $e->type->value, 'type' => $e->type->value, 'detail' => $e->detail, 'at' => null, 'href' => "/eventos/{$e->id}", 'state' => 'done',
            ], $e->occurred_on->toDateString()));

        // Actividad diaria: solo cuántos registros hubo de cada tipo.
        FeedLog::where('baby_id', $baby->id)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->pluck('occurred_at')->each(fn ($at) => $count('feed', $at));
        SleepLog::where('baby_id', $baby->id)->where('started_at', '>=', $from)->where('started_at', '<', $to)->pluck('started_at')->each(fn ($at) => $count('sleep', $at));
        DiaperLog::where('baby_id', $baby->id)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->pluck('occurred_at')->each(fn ($at) => $count('diaper', $at));
        MedicineLog::where('baby_id', $baby->id)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->pluck('occurred_at')->each(fn ($at) => $count('medicine', $at));

        foreach ($days as &$day) {
            $day['items'] = collect($day['items'] ?? [])->sortBy(fn ($i) => $i['at'] ?? '0')->values()->all();
            $day['counts'] = $day['counts'] ?? [];
        }

        ksort($days);

        return ['month' => $month, 'days' => $days];
    }
}
