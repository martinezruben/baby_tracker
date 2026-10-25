<?php

namespace App\Listeners;

use App\Actions\RecordDose;
use App\Enums\DoseStatus;
use App\Events\ReminderAcknowledged;
use App\Models\Prescription;
use App\Models\PrescriptionDose;

/** Confirmar el aviso de una toma la da por administrada y la deja en el registro de medicinas. */
class LogDoseWhenAcknowledged
{
    public function __construct(private RecordDose $doses) {}

    public function handle(ReminderAcknowledged $event): void
    {
        $rule = $event->occurrence->rule;

        if (! $rule->subject instanceof Prescription) {
            return;
        }

        $dose = PrescriptionDose::query()
            ->where('prescription_id', $rule->subject->id)
            ->where('scheduled_at', $event->occurrence->event_at)
            ->where('status', DoseStatus::Pending)
            ->first();

        if ($dose) {
            $this->doses->give($dose, $event->by);
        }
    }
}
