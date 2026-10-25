<?php

namespace App\Actions;

use App\Enums\AckScope;
use App\Enums\AppointmentStatus;
use App\Enums\MeasurementType;
use App\Enums\NotificationEventType;
use App\Models\Appointment;
use App\Models\Baby;
use App\Models\Caretaker;
use App\Models\Measurement;
use App\Models\NotificationRule;
use App\Services\Notifications\NotificationScheduler;
use App\Support\FamilyClock;
use Illuminate\Support\Facades\DB;

class SaveAppointment
{
    public function __construct(private NotificationScheduler $scheduler) {}

    /**
     * Crea o actualiza una cita: sus datos, lo medido en ella (que también entra en la curva de
     * crecimiento) y su recordatorio con varios avisos.
     *
     * @param  array<string, mixed>  $data  campos de la cita (fechas ya en UTC) + remind y offsets_minutes
     * @param  array<string, float|string|null>  $vitals  tipo => valor
     */
    public function handle(Caretaker $by, Baby $baby, array $data, array $vitals, ?Appointment $appointment = null): Appointment
    {
        return DB::transaction(function () use ($by, $baby, $data, $vitals, $appointment) {
            $remind = (bool) ($data['remind'] ?? false);
            $offsets = $data['offsets_minutes'] ?? [-1440, -60];
            unset($data['remind'], $data['offsets_minutes']);

            $status = AppointmentStatus::from($data['status']);
            $data['completed_at'] = $status === AppointmentStatus::Completed
                ? ($appointment?->completed_at ?? now())
                : null;

            if ($appointment) {
                $appointment->update($data);
            } else {
                $appointment = Appointment::create($data + [
                    'family_id' => $baby->family_id,
                    'baby_id' => $baby->id,
                    'created_by' => $by->id,
                ]);
            }

            $this->syncVitals($appointment, $baby, $by, $vitals);
            $this->syncReminder($appointment, $baby, $by, $remind, $offsets);

            return $appointment;
        });
    }

    /** Borrar la cita apaga su recordatorio. */
    public function delete(Appointment $appointment): void
    {
        DB::transaction(function () use ($appointment) {
            if ($appointment->reminderRule) {
                $this->scheduler->cancelAll($appointment->reminderRule);
                $appointment->reminderRule->update(['is_active' => false]);
            }

            $appointment->delete();
        });
    }

    private function syncVitals(Appointment $appointment, Baby $baby, Caretaker $by, array $vitals): void
    {
        $settings = $baby->family->settings;
        $clock = FamilyClock::for($baby->family);
        $day = $appointment->scheduled_at->copy()->setTimezone($clock->timezone)->toDateString();

        foreach (MeasurementType::cases() as $type) {
            $value = $vitals[$type->value] ?? null;
            $existing = $appointment->measurements()->where('type', $type)->first();

            if ($value === null || $value === '') {
                $existing?->delete();

                continue;
            }

            $attributes = [
                'measured_on' => $day,
                'value' => $value,
                'unit' => match ($type) {
                    MeasurementType::Weight => $settings->weight_unit,
                    MeasurementType::Temperature => $settings->temperature_unit,
                    default => $settings->height_unit,
                },
            ];

            $existing
                ? $existing->update($attributes)
                : Measurement::create($attributes + [
                    'family_id' => $baby->family_id,
                    'baby_id' => $baby->id,
                    'caretaker_id' => $by->id,
                    'appointment_id' => $appointment->id,
                    'type' => $type,
                ]);
        }
    }

    /** Solo las citas programadas y futuras avisan; al cancelarse o realizarse, se apagan los avisos. */
    private function syncReminder(Appointment $appointment, Baby $baby, Caretaker $by, bool $remind, array $offsets): void
    {
        $rule = $appointment->reminderRule;

        if ($rule) {
            $this->scheduler->cancelAll($rule);
        }

        $active = $remind
            && $appointment->status === AppointmentStatus::Scheduled
            && $appointment->scheduled_at->isFuture();

        if (! $rule && ! $active) {
            return;
        }

        $locale = $by->language ?? $baby->family->settings->language ?? config('app.locale');
        $fields = [
            'title' => trans('ui.citas.reminder_title', ['reason' => $appointment->reason], $locale),
            'body' => collect([$appointment->doctor_name, $appointment->location])->filter()->implode(' · ') ?: null,
            'event_at' => $appointment->scheduled_at,
            'offsets_minutes' => collect($offsets)->map(fn ($m) => (int) $m)->sort()->values()->all(),
            'is_active' => $active,
        ];

        if ($rule) {
            $rule->update($fields);
        } else {
            $rule = NotificationRule::create($fields + [
                'family_id' => $baby->family_id,
                'baby_id' => $baby->id,
                'created_by' => $by->id,
                'subject_type' => Appointment::class,
                'subject_id' => $appointment->id,
                'event_type' => NotificationEventType::Appointment,
                'ack_scope' => AckScope::Any,
                'requires_ack' => false,
                'snooze_options' => [10, 30, 60, 120],
                'channels' => ['push'],
            ]);
            $appointment->update(['reminder_rule_id' => $rule->id]);
        }

        $rule->recipients()->sync($baby->family->caretakers()->where('is_active', true)->pluck('id'));

        if ($active) {
            $this->scheduler->schedule($rule, $appointment->scheduled_at);
        }
    }
}
