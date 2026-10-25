<?php

namespace App\Services\Notifications;

use App\Enums\OccurrenceStatus;
use App\Enums\TargetStatus;
use App\Models\NotificationOccurrence;
use App\Models\NotificationRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationScheduler
{
    /**
     * Programa los avisos de un evento: una ocurrencia por cada desfase de la regla
     * (por ejemplo -1440, -60 y 0 minutos) y un destino por destinatario.
     * Es idempotente: volver a programar el mismo evento no duplica nada.
     *
     * @return Collection<int, NotificationOccurrence>
     */
    public function schedule(NotificationRule $rule, CarbonInterface $eventAt): Collection
    {
        $recipients = $rule->recipients()->get();

        return DB::transaction(function () use ($rule, $eventAt, $recipients) {
            return collect($rule->offsets_minutes)->unique()->map(function (int $offset) use ($rule, $eventAt, $recipients) {
                $occurrence = NotificationOccurrence::firstOrCreate(
                    [
                        'notification_rule_id' => $rule->id,
                        'event_at' => $eventAt->copy()->utc(),
                        'offset_minutes' => $offset,
                    ],
                    ['status' => OccurrenceStatus::Active],
                );

                if ($occurrence->wasRecentlyCreated) {
                    $fireAt = $eventAt->copy()->utc()->addMinutes($offset);

                    foreach ($recipients as $caretaker) {
                        $occurrence->targets()->create([
                            'caretaker_id' => $caretaker->id,
                            'status' => TargetStatus::Pending,
                            'next_send_at' => $fireAt,
                        ]);
                    }
                }

                return $occurrence;
            });
        });
    }

    /**
     * El evento ya ocurrió o se hizo (medicina dada, cita atendida): cancela los avisos pendientes.
     */
    public function completeEvent(NotificationRule $rule, CarbonInterface $eventAt): void
    {
        DB::transaction(function () use ($rule, $eventAt) {
            $rule->occurrences()
                ->where('event_at', $eventAt->copy()->utc())
                ->where('status', OccurrenceStatus::Active)
                ->get()
                ->each(function (NotificationOccurrence $occurrence) {
                    $occurrence->targets()
                        ->where('status', TargetStatus::Pending)
                        ->update(['status' => TargetStatus::Cancelled, 'next_send_at' => null]);
                    $occurrence->update(['status' => OccurrenceStatus::Cancelled]);
                });
        });
    }
}
