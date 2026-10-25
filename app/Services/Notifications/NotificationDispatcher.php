<?php

namespace App\Services\Notifications;

use App\Contracts\ReminderChannel;
use App\Enums\DeliveryStatus;
use App\Enums\OccurrenceStatus;
use App\Enums\TargetStatus;
use App\Models\NotificationRule;
use App\Models\NotificationTarget;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class NotificationDispatcher
{
    public function __construct(
        private QuietHours $quietHours,
        private NotificationActions $actions,
    ) {}

    /**
     * Envía todos los avisos que vencen en $now. Pensado para correr cada minuto.
     * Devuelve cuántos destinos se procesaron.
     */
    public function dispatchDue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $processed = 0;

        NotificationTarget::query()
            ->where('status', TargetStatus::Pending)
            ->where('next_send_at', '<=', $now)
            ->whereHas('occurrence', fn ($q) => $q->where('status', OccurrenceStatus::Active)
                ->whereHas('rule', fn ($r) => $r->where('is_active', true)))
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use ($now, &$processed) {
                DB::transaction(function () use ($id, $now, &$processed) {
                    $target = NotificationTarget::query()->lockForUpdate()->find($id);

                    // Otro proceso ya lo atendió, o se confirmó/pospuso entre medias.
                    if (! $target || $target->status !== TargetStatus::Pending
                        || ! $target->next_send_at || $target->next_send_at->greaterThan($now)) {
                        return;
                    }

                    $this->process($target, $now);
                    $processed++;
                });
            });

        return $processed;
    }

    private function process(NotificationTarget $target, CarbonInterface $now): void
    {
        $occurrence = $target->occurrence;
        $rule = $occurrence->rule;

        $quietEnd = $this->quietHours->endsAt($rule, $now, $this->timezoneFor($rule));
        if ($quietEnd) {
            $target->update(['next_send_at' => $quietEnd]);

            return;
        }

        if (! $this->deliver($target, $rule)) {
            $this->registerFailure($target, $now);
            $this->actions->refreshOccurrence($occurrence);

            return;
        }

        $sent = $target->sent_count + 1;
        $next = $this->nextSend($rule, $sent, $now);

        $target->update([
            'sent_count' => $sent,
            'failed_attempts' => 0,
            'last_sent_at' => $now,
            'snoozed_until' => null,
            'next_send_at' => $next,
            'status' => $next
                ? TargetStatus::Pending
                : ($rule->requires_ack && $rule->repeat_every_minutes ? TargetStatus::Exhausted : TargetStatus::Sent),
        ]);

        $this->actions->refreshOccurrence($occurrence);
    }

    private function nextSend(NotificationRule $rule, int $sent, CarbonInterface $now): ?CarbonInterface
    {
        if (! $rule->repeat_every_minutes) {
            return null;
        }

        $repeatsDone = $sent - 1;

        $cap = $rule->max_repeats
            ?? ($rule->requires_ack
                ? config('reminders.hard_cap_repeats')
                : config('reminders.default_max_repeats'));

        return $repeatsDone >= $cap ? null : $now->copy()->addMinutes($rule->repeat_every_minutes);
    }

    /** Un destino cuenta como entregado si al menos un canal lo envió. */
    private function deliver(NotificationTarget $target, NotificationRule $rule): bool
    {
        $payload = [
            'title' => $rule->title,
            'body' => $rule->body,
            'target_id' => $target->id,
            'occurrence_id' => $target->notification_occurrence_id,
            'event_type' => $rule->event_type->value,
            'snooze_options' => $rule->snooze_options,
            'requires_ack' => $rule->requires_ack,
        ];

        $delivered = false;

        foreach ($rule->channels as $name) {
            $class = config("reminders.channels.{$name}");

            try {
                if (! $class) {
                    throw new \RuntimeException("Canal de aviso desconocido: {$name}");
                }

                /** @var ReminderChannel $channel */
                $channel = app($class);
                $channel->send($target->caretaker, $payload);

                $target->deliveries()->create(['channel' => $name, 'status' => DeliveryStatus::Sent, 'sent_at' => now()]);
                $delivered = true;
            } catch (Throwable $e) {
                $target->deliveries()->create([
                    'channel' => $name,
                    'status' => DeliveryStatus::Failed,
                    'error' => mb_substr($e->getMessage(), 0, 500),
                    'sent_at' => now(),
                ]);
            }
        }

        return $delivered;
    }

    private function registerFailure(NotificationTarget $target, CarbonInterface $now): void
    {
        $attempts = $target->failed_attempts + 1;
        $giveUp = $attempts >= config('reminders.max_failed_attempts');

        $target->update([
            'failed_attempts' => $attempts,
            'status' => $giveUp ? TargetStatus::Failed : TargetStatus::Pending,
            'next_send_at' => $giveUp ? null : $now->copy()->addMinutes(config('reminders.retry_after_minutes')),
        ]);
    }

    private function timezoneFor(NotificationRule $rule): string
    {
        return $rule->family->settings->timezone ?? config('app.timezone');
    }
}
