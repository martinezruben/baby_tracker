<?php

namespace App\Actions;

use App\Enums\DoseStatus;
use App\Enums\PrescriptionStatus;
use App\Models\Caretaker;
use App\Models\MedicineLog;
use App\Models\PrescriptionDose;
use App\Services\Notifications\NotificationScheduler;
use Illuminate\Support\Facades\DB;

class RecordDose
{
    public function __construct(private NotificationScheduler $scheduler) {}

    /** Da la toma por administrada: queda en el registro de medicinas y se apagan sus avisos. */
    public function give(PrescriptionDose $dose, Caretaker $by): void
    {
        DB::transaction(function () use ($dose, $by) {
            $dose = PrescriptionDose::query()->lockForUpdate()->findOrFail($dose->id);

            if ($dose->status !== DoseStatus::Pending) {
                return;
            }

            $prescription = $dose->prescription;

            $log = MedicineLog::create([
                'family_id' => $prescription->family_id,
                'baby_id' => $prescription->baby_id,
                'caretaker_id' => $by->id,
                'medicine_id' => $prescription->medicine_id,
                'occurred_at' => now(),
                'dose' => $prescription->dose,
                'unit' => $prescription->unit,
            ]);

            $dose->update([
                'status' => DoseStatus::Given,
                'given_at' => now(),
                'given_by' => $by->id,
                'medicine_log_id' => $log->id,
            ]);

            $this->stopNotices($dose);
            $this->refreshPrescription($dose);
        });
    }

    /** "Omitir": no se dio y no se va a dar; no entra en el registro de medicinas. */
    public function skip(PrescriptionDose $dose, Caretaker $by): void
    {
        DB::transaction(function () use ($dose, $by) {
            if ($dose->status !== DoseStatus::Pending) {
                return;
            }

            $dose->update(['status' => DoseStatus::Skipped, 'given_by' => $by->id]);

            $this->stopNotices($dose);
            $this->refreshPrescription($dose);
        });
    }

    /** Deshacer: la toma vuelve a pendiente y se borra su entrada del registro de medicinas. */
    public function revert(PrescriptionDose $dose): void
    {
        DB::transaction(function () use ($dose) {
            if ($dose->status === DoseStatus::Pending) {
                return;
            }

            $dose->medicineLog?->delete();

            $dose->update(['status' => DoseStatus::Pending, 'given_at' => null, 'given_by' => null, 'medicine_log_id' => null]);

            $this->refreshPrescription($dose);
        });
    }

    private function stopNotices(PrescriptionDose $dose): void
    {
        $rule = $dose->prescription->rule;

        if ($rule) {
            $this->scheduler->completeEvent($rule, $dose->scheduled_at);
        }
    }

    /** La receta se completa cuando no queda ninguna toma pendiente; si se deshace una, vuelve a estar activa. */
    private function refreshPrescription(PrescriptionDose $dose): void
    {
        $prescription = $dose->prescription;

        if ($prescription->status === PrescriptionStatus::Cancelled) {
            return;
        }

        $prescription->update([
            'status' => $prescription->doses()->where('status', DoseStatus::Pending)->exists()
                ? PrescriptionStatus::Active
                : PrescriptionStatus::Completed,
        ]);
    }
}
