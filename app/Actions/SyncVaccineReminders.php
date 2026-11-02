<?php

namespace App\Actions;

use App\Enums\AckScope;
use App\Enums\NotificationEventType;
use App\Models\Baby;
use App\Models\NotificationRule;
use App\Models\VaccineDose;
use App\Services\Notifications\NotificationScheduler;
use App\Support\VaccineSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SyncVaccineReminders
{
    public const OFFSETS = [-10080, -1440, 0]; // 1 semana antes, 1 día antes y el día de la dosis

    /** Hasta cuántos días por delante se programan las repeticiones; `vaccines:sync` (diario) corre la ventana. */
    public const WINDOW_DAYS = 60;

    public function __construct(private NotificationScheduler $scheduler) {}

    /**
     * Deja un recordatorio por cada dosis del esquema que aún no se aplicó (una regla por bebé y dosis):
     * 1 semana antes, 1 día antes y el día de la dosis y, si ya venció, cada `vaccine_repeat_days` días hasta que se
     * aplique (0 = no se repite), siempre a la hora local `vaccine_notify_time`. Apaga el de las que se aplicaron o se
     * borraron. Solo se programan avisos futuros. Es seguro llamarlo las veces que haga falta: solo cambia lo que cambió.
     */
    public function handle(Baby $baby): void
    {
        DB::transaction(function () use ($baby) {
            $schedule = VaccineSchedule::for($baby);
            $rows = $schedule->rows($baby);
            $caretakers = $baby->family->caretakers()->where('is_active', true)->pluck('id');
            $repeat = (int) ($baby->family->settings->vaccine_repeat_days ?? 0);

            foreach ($rows as $row) {
                /** @var VaccineDose $dose */
                $dose = $row['dose'];
                $rule = $this->ruleFor($baby, $dose->id);
                $wanted = $row['status'] !== 'applied' && ($row['days_left'] >= 0 || $repeat > 0);

                if (! $wanted) {
                    $this->turnOff($rule);

                    continue;
                }

                $eventAt = $schedule->dueAt($row['due_on']);
                $offsets = $this->offsets($schedule, $row['due_on'], $eventAt, $repeat);
                $fields = [
                    'title' => trans('ui.vacunas.reminder_title', ['vaccine' => trim($dose->vaccine_name.' '.$dose->dose_label)], $baby->family->settings->language),
                    'body' => $dose->notes,
                    'event_at' => $eventAt,
                    'is_active' => true,
                ];

                if (! $rule) {
                    $rule = NotificationRule::create($fields + [
                        'family_id' => $baby->family_id,
                        'baby_id' => $baby->id,
                        'subject_type' => VaccineDose::class,
                        'subject_id' => $dose->id,
                        'event_type' => NotificationEventType::Vaccine,
                        'offsets_minutes' => $offsets,
                        'ack_scope' => AckScope::Any,
                        'requires_ack' => false,
                        'snooze_options' => [60, 120, 1440],
                        'channels' => ['push'],
                    ]);
                } elseif ($rule->is_active && $rule->event_at->equalTo($eventAt) && $rule->title === $fields['title'] && $rule->offsets_minutes === $offsets) {
                    $rule->recipients()->sync($caretakers);

                    continue; // sin cambios: no se tocan los avisos ya programados
                } elseif ($rule->is_active && $rule->event_at->equalTo($eventAt) && $rule->title === $fields['title']) {
                    // Solo cambió la lista de repeticiones: se quitan las que sobran y se añaden las nuevas, sin volver a avisar lo ya enviado.
                    $this->scheduler->cancelOffsetsNotIn($rule, $offsets);
                    $rule->update(['offsets_minutes' => $offsets]);
                } else {
                    $this->scheduler->cancelAll($rule);
                    $rule->update($fields + ['offsets_minutes' => $offsets]);
                }

                $rule->recipients()->sync($caretakers);
                $this->scheduler->schedule($rule, $eventAt);
            }

            // Reglas de dosis que ya no existen en el esquema.
            NotificationRule::query()
                ->where('baby_id', $baby->id)
                ->where('subject_type', VaccineDose::class)
                ->where('is_active', true)
                ->whereNotIn('subject_id', $rows->pluck('dose.id'))
                ->get()
                ->each(fn (NotificationRule $rule) => $this->turnOff($rule));
        });
    }

    /**
     * Minutos respecto a `$eventAt` de cada aviso que todavía no pasó: los de antes de la fecha y las repeticiones
     * (fecha + k·N días a la misma hora local) mientras sigan dentro de la ventana.
     *
     * @return array<int, int>
     */
    private function offsets(VaccineSchedule $schedule, CarbonImmutable $dueOn, CarbonImmutable $eventAt, int $repeat): array
    {
        $now = now();
        $fires = collect(self::OFFSETS)->map(fn (int $o) => $eventAt->addMinutes($o));

        $maxK = $repeat > 0 ? (int) ceil((max(0, (int) $dueOn->diffInDays($now, false)) + self::WINDOW_DAYS) / $repeat) : 0;

        for ($k = 1; $k <= $maxK; $k++) {
            $fire = $schedule->dueAt($dueOn->addDays($k * $repeat));
            if ($fire->greaterThan($now->copy()->addDays(self::WINDOW_DAYS))) {
                break;
            }
            $fires->push($fire);
        }

        return $fires->filter(fn (CarbonImmutable $f) => $f->greaterThan($now))
            ->map(fn (CarbonImmutable $f) => (int) $eventAt->diffInMinutes($f, false))
            ->unique()->sort()->values()->all();
    }

    private function ruleFor(Baby $baby, int $doseId): ?NotificationRule
    {
        return NotificationRule::where('baby_id', $baby->id)
            ->where('subject_type', VaccineDose::class)
            ->where('subject_id', $doseId)
            ->first();
    }

    private function turnOff(?NotificationRule $rule): void
    {
        if ($rule && $rule->is_active) {
            $this->scheduler->cancelAll($rule);
            $rule->update(['is_active' => false]);
        }
    }
}
