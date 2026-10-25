<?php

namespace App\Actions;

use App\Enums\AckScope;
use App\Enums\NotificationEventType;
use App\Models\Baby;
use App\Models\NotificationRule;
use App\Models\VaccineDose;
use App\Services\Notifications\NotificationScheduler;
use App\Support\VaccineSchedule;
use Illuminate\Support\Facades\DB;

class SyncVaccineReminders
{
    public const OFFSETS = [-10080, -1440, 0]; // 1 semana antes, 1 día antes y el día de la dosis

    public function __construct(private NotificationScheduler $scheduler) {}

    /**
     * Deja un recordatorio por cada dosis del esquema que aún no se aplicó y todavía no pasó su fecha
     * (una regla por bebé y dosis), y apaga el de las que ya se aplicaron, vencieron o se borraron.
     * Es seguro llamarlo las veces que haga falta: solo reprograma lo que cambió.
     */
    public function handle(Baby $baby): void
    {
        DB::transaction(function () use ($baby) {
            $schedule = VaccineSchedule::for($baby);
            $rows = $schedule->rows($baby);
            $caretakers = $baby->family->caretakers()->where('is_active', true)->pluck('id');

            foreach ($rows as $row) {
                /** @var VaccineDose $dose */
                $dose = $row['dose'];
                $rule = $this->ruleFor($baby, $dose->id);
                $wanted = $row['status'] !== 'applied' && $row['days_left'] >= 0;

                if (! $wanted) {
                    $this->turnOff($rule);

                    continue;
                }

                $eventAt = $schedule->dueAt($row['due_on']);
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
                        'offsets_minutes' => self::OFFSETS,
                        'ack_scope' => AckScope::Any,
                        'requires_ack' => false,
                        'snooze_options' => [60, 120, 1440],
                        'channels' => ['push'],
                    ]);
                } elseif ($rule->is_active && $rule->event_at->equalTo($eventAt) && $rule->title === $fields['title']) {
                    $rule->recipients()->sync($caretakers);

                    continue; // sin cambios: no se tocan los avisos ya programados
                } else {
                    $this->scheduler->cancelAll($rule);
                    $rule->update($fields);
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
