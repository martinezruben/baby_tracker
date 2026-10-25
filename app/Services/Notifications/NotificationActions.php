<?php

namespace App\Services\Notifications;

use App\Enums\AckScope;
use App\Enums\OccurrenceStatus;
use App\Enums\TargetStatus;
use App\Models\Caretaker;
use App\Models\NotificationOccurrence;
use App\Models\NotificationTarget;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NotificationActions
{
    /**
     * Confirma el aviso. Con alcance "any" (por defecto) se detiene para todos los
     * cuidadores en cuanto uno acepta; con "each" solo para quien lo acepta.
     */
    public function acknowledge(NotificationTarget $target, Caretaker $by): void
    {
        DB::transaction(function () use ($target, $by) {
            $occurrence = $target->occurrence()->lockForUpdate()->firstOrFail();
            $rule = $occurrence->rule;

            $query = $occurrence->targets()->whereIn('status', [TargetStatus::Pending, TargetStatus::Sent, TargetStatus::Exhausted]);

            if ($rule->ack_scope === AckScope::Each) {
                $query->whereKey($target->id);
            }

            $query->update([
                'status' => TargetStatus::Acknowledged,
                'next_send_at' => null,
                'snoozed_until' => null,
                'acknowledged_at' => now(),
                'acknowledged_by' => $by->id,
            ]);

            $this->refreshOccurrence($occurrence);
        });
    }

    /**
     * Pospone el aviso. Solo se aceptan los minutos que la regla permite.
     * Con alcance "any" se pospone para todos; con "each", solo para este cuidador.
     */
    public function snooze(NotificationTarget $target, int $minutes): void
    {
        $occurrence = $target->occurrence;
        $rule = $occurrence->rule;

        if (! in_array($minutes, $rule->snooze_options, true)) {
            throw new InvalidArgumentException("Snooze de {$minutes} minutos no permitido para esta regla.");
        }

        $until = now()->addMinutes($minutes);

        DB::transaction(function () use ($target, $occurrence, $rule, $until) {
            $query = $occurrence->targets()->whereIn('status', [TargetStatus::Pending, TargetStatus::Sent, TargetStatus::Exhausted]);

            if ($rule->ack_scope === AckScope::Each) {
                $query->whereKey($target->id);
            }

            $query->update([
                'status' => TargetStatus::Pending,
                'next_send_at' => $until,
                'snoozed_until' => $until,
            ]);

            if ($occurrence->status === OccurrenceStatus::Finished) {
                $occurrence->update(['status' => OccurrenceStatus::Active]);
            }
        });
    }

    public function refreshOccurrence(NotificationOccurrence $occurrence): void
    {
        $statuses = $occurrence->targets()->pluck('status');

        if ($statuses->isEmpty()) {
            return;
        }

        if ($statuses->contains(TargetStatus::Pending)) {
            return;
        }

        $occurrence->update([
            'status' => $statuses->contains(TargetStatus::Acknowledged)
                ? OccurrenceStatus::Acknowledged
                : OccurrenceStatus::Finished,
        ]);
    }
}
