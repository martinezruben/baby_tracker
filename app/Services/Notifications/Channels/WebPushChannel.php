<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\ReminderChannel;
use App\Models\Caretaker;
use App\Models\PushSubscription;
use App\Services\Notifications\Push\PushClient;
use Illuminate\Support\Facades\URL;

class WebPushChannel implements ReminderChannel
{
    public function __construct(private PushClient $client) {}

    public function send(Caretaker $caretaker, array $payload): void
    {
        $subscriptions = $caretaker->pushSubscriptions;

        if ($subscriptions->isEmpty()) {
            throw new \RuntimeException('El cuidador no tiene dispositivos con avisos activados.');
        }

        $body = json_encode($this->notification($caretaker, $payload), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $delivered = 0;
        $lastReason = null;

        foreach ($subscriptions as $subscription) {
            $result = $this->client->send($subscription, $body);

            if ($result->delivered) {
                $delivered++;
                $subscription->update(['failure_count' => 0, 'last_used_at' => now()]);

                continue;
            }

            $lastReason = $result->reason;
            $this->registerFailure($subscription, $result->expired);
        }

        if ($delivered === 0) {
            throw new \RuntimeException('No se pudo entregar a ningún dispositivo: '.($lastReason ?? 'sin motivo'));
        }
    }

    /**
     * Contenido del aviso. Solo texto de la regla: nunca fotos ni ubicación.
     * Los botones llevan enlaces firmados para confirmar o posponer sin abrir la app.
     */
    private function notification(Caretaker $caretaker, array $payload): array
    {
        $snooze = $this->defaultSnooze($payload['snooze_options']);
        $locale = $caretaker->language ?? $caretaker->family?->settings?->language ?? config('app.locale');
        $expires = now()->addHours(config('webpush.action_ttl_hours'));

        $actionUrl = fn (array $query) => URL::temporarySignedRoute(
            'reminders.act', $expires, ['target' => $payload['target_id']] + $query, absolute: false,
        );

        $actions = [[
            'action' => 'ack',
            'title' => __('ui.push.ack', [], $locale),
        ]];
        $data = [
            'target_id' => $payload['target_id'],
            'open_url' => route('inicio'),
            'ack_url' => $actionUrl(['action' => 'ack']),
        ];

        if ($snooze) {
            $actions[] = ['action' => 'snooze', 'title' => __('ui.push.snooze', ['minutes' => $snooze], $locale)];
            $data['snooze_url'] = $actionUrl(['action' => 'snooze', 'minutes' => $snooze]);
        }

        return [
            'title' => $payload['title'],
            'body' => $payload['body'],
            'tag' => 'reminder-'.$payload['occurrence_id'],
            'requireInteraction' => $payload['requires_ack'],
            'actions' => $actions,
            'data' => $data,
        ];
    }

    /** Opción de snooze del botón: la de 10 minutos si existe, si no la primera. */
    private function defaultSnooze(array $options): ?int
    {
        if ($options === []) {
            return null;
        }

        return in_array(10, $options, true) ? 10 : $options[0];
    }

    private function registerFailure(PushSubscription $subscription, bool $expired): void
    {
        if ($expired || $subscription->failure_count + 1 >= config('webpush.max_failures')) {
            $subscription->delete();

            return;
        }

        $subscription->increment('failure_count');
    }
}
