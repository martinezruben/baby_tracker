<?php

namespace App\Actions;

use App\Enums\AckScope;
use App\Enums\DoseStatus;
use App\Enums\NotificationEventType;
use App\Enums\PrescriptionStatus;
use App\Models\Appointment;
use App\Models\Baby;
use App\Models\Caretaker;
use App\Models\Medicine;
use App\Models\NotificationRule;
use App\Models\Prescription;
use App\Services\Notifications\NotificationScheduler;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SavePrescription
{
    public function __construct(private NotificationScheduler $scheduler) {}

    /**
     * Crea la receta, genera todas sus tomas (cada `interval_hours` desde `starts_at`) y programa un
     * aviso por toma que insiste hasta que alguien lo confirma. Confirmarlo registra la toma.
     *
     * @param  array{medicine_id?: ?int, new_medicine?: ?string, dose: float|int|string, unit?: ?string, interval_hours: int, total_doses: int, starts_at: CarbonInterface, instructions?: ?string}  $data
     */
    public function handle(Caretaker $by, Baby $baby, ?Appointment $appointment, array $data): Prescription
    {
        return DB::transaction(function () use ($by, $baby, $appointment, $data) {
            $medicine = ! empty($data['new_medicine'])
                ? Medicine::firstOrCreate(
                    ['family_id' => $baby->family_id, 'name' => trim($data['new_medicine'])],
                    ['typical_dose' => $data['dose'], 'unit' => $data['unit'] ?? null],
                )
                : Medicine::where('family_id', $baby->family_id)->findOrFail($data['medicine_id']);

            $prescription = Prescription::create([
                'family_id' => $baby->family_id,
                'baby_id' => $baby->id,
                'appointment_id' => $appointment?->id,
                'medicine_id' => $medicine->id,
                'created_by' => $by->id,
                'dose' => $data['dose'],
                'unit' => $data['unit'] ?? null,
                'interval_hours' => $data['interval_hours'],
                'total_doses' => $data['total_doses'],
                'starts_at' => $data['starts_at'],
                'instructions' => $data['instructions'] ?? null,
                'status' => PrescriptionStatus::Active,
            ]);

            $times = collect(range(0, $data['total_doses'] - 1))
                ->map(fn (int $i) => $data['starts_at']->copy()->utc()->addHours($i * $data['interval_hours']));

            $times->each(fn ($at) => $prescription->doses()->create(['scheduled_at' => $at, 'status' => DoseStatus::Pending]));

            $rule = NotificationRule::create([
                'family_id' => $baby->family_id,
                'baby_id' => $baby->id,
                'created_by' => $by->id,
                'subject_type' => Prescription::class,
                'subject_id' => $prescription->id,
                'event_type' => NotificationEventType::Medicine,
                'title' => trim($medicine->name.' · '.$this->doseLabel($data)),
                'body' => $data['instructions'] ?? null,
                'event_at' => $data['starts_at']->copy()->utc(),
                'offsets_minutes' => [0],
                'repeat_every_minutes' => 10,
                'requires_ack' => true,
                'ack_scope' => AckScope::Any,
                'snooze_options' => [5, 10, 15, 30],
                'respect_quiet_hours' => false, // una toma de medicina no espera a la mañana
                'channels' => ['push'],
                'is_active' => true,
            ]);
            $rule->recipients()->sync($baby->family->caretakers()->where('is_active', true)->pluck('id'));
            $prescription->update(['notification_rule_id' => $rule->id]);

            // No se programan avisos para tomas que ya pasaron hace rato (evita una avalancha al registrar una receta antigua).
            $times->filter(fn ($at) => $at->greaterThanOrEqualTo(now()->subMinutes(10)))
                ->each(fn ($at) => $this->scheduler->schedule($rule, $at));

            return $prescription;
        });
    }

    /** Suspender el tratamiento: se cancelan los avisos y las tomas pendientes quedan como omitidas. */
    public function cancel(Prescription $prescription): void
    {
        DB::transaction(function () use ($prescription) {
            if ($prescription->rule) {
                $this->scheduler->cancelAll($prescription->rule);
                $prescription->rule->update(['is_active' => false]);
            }

            $prescription->doses()->where('status', DoseStatus::Pending)->update(['status' => DoseStatus::Skipped]);
            $prescription->update(['status' => PrescriptionStatus::Cancelled]);
        });
    }

    private function doseLabel(array $data): string
    {
        $dose = rtrim(rtrim(number_format((float) $data['dose'], 2, '.', ''), '0'), '.');

        return trim($dose.' '.($data['unit'] ?? ''));
    }
}
