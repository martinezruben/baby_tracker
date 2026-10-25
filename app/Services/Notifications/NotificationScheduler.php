<?php

namespace App\Services\Notifications;

use App\Enums\OccurrenceStatus;
use App\Enums\TargetStatus;
use App\Models\Caretaker;
use App\Models\NotificationOccurrence;
use App\Models\NotificationRule;
use App\Models\NotificationTarget;
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
                $occurrence = NotificationOccurrence::firstOrNew([
                    'notification_rule_id' => $rule->id,
                    'event_at' => $eventAt->copy()->utc(),
                    'offset_minutes' => $offset,
                ]);

                // Nueva, o cancelada antes (al editar la regla): se (re)programa desde cero.
                $needsTargets = ! $occurrence->exists || $occurrence->status === OccurrenceStatus::Cancelled;

                if ($needsTargets) {
                    $occurrence->targets()->delete();
                    $occurrence->status = OccurrenceStatus::Active;
                    $occurrence->save();

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

    /**
     * Cancela todos los avisos pendientes de la regla (al editarla, desactivarla o borrarla).
     */
    public function cancelAll(NotificationRule $rule): void
    {
        DB::transaction(function () use ($rule) {
            $rule->occurrences()
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

    /**
     * Suma a un cuidador a una regla y le crea los avisos de lo que todavía no ha pasado.
     * Sirve para quien se une a la familia (o se reactiva) con citas, tomas y vacunas ya programadas.
     */
    public function addRecipient(NotificationRule $rule, Caretaker $caretaker): void
    {
        DB::transaction(function () use ($rule, $caretaker) {
            $rule->recipients()->syncWithoutDetaching([$caretaker->id]);

            $rule->occurrences()->where('status', OccurrenceStatus::Active)->get()->each(function (NotificationOccurrence $occurrence) use ($caretaker) {
                $fireAt = $occurrence->event_at->copy()->utc()->addMinutes($occurrence->offset_minutes);

                if ($fireAt->lessThan(now())) {
                    return;
                }

                $target = $occurrence->targets()->where('caretaker_id', $caretaker->id)->first();

                if (! $target) {
                    $occurrence->targets()->create([
                        'caretaker_id' => $caretaker->id,
                        'status' => TargetStatus::Pending,
                        'next_send_at' => $fireAt,
                    ]);
                } elseif ($target->status === TargetStatus::Cancelled) {
                    // Estaba dado de baja: se reactivan sus avisos pendientes desde cero.
                    $target->update(['status' => TargetStatus::Pending, 'next_send_at' => $fireAt, 'sent_count' => 0, 'failed_attempts' => 0]);
                }
            });
        });
    }

    /** Quita a un cuidador de todas las reglas y cancela sus avisos pendientes. */
    public function removeRecipient(Caretaker $caretaker): void
    {
        DB::transaction(function () use ($caretaker) {
            NotificationTarget::query()
                ->where('caretaker_id', $caretaker->id)
                ->where('status', TargetStatus::Pending)
                ->update(['status' => TargetStatus::Cancelled, 'next_send_at' => null]);

            DB::table('notification_rule_caretaker')->where('caretaker_id', $caretaker->id)->delete();
        });
    }
}
