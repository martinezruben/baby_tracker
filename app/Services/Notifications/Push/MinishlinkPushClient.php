<?php

namespace App\Services\Notifications\Push;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class MinishlinkPushClient implements PushClient
{
    public function send(PushSubscription $subscription, string $payload): PushResult
    {
        $report = $this->webPush()->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'keys' => ['p256dh' => $subscription->p256dh, 'auth' => $subscription->auth],
                'contentEncoding' => $subscription->content_encoding,
            ]),
            $payload,
            ['TTL' => config('webpush.ttl_seconds'), 'urgency' => 'high'],
        );

        return new PushResult(
            delivered: $report->isSuccess(),
            expired: $report->isSubscriptionExpired(),
            reason: $report->isSuccess() ? null : $report->getReason(),
        );
    }

    private function webPush(): WebPush
    {
        $vapid = config('webpush.vapid');

        if (! $vapid['public_key'] || ! $vapid['private_key']) {
            throw new \RuntimeException('Faltan las claves VAPID. Ejecuta `php artisan webpush:vapid` y copia las claves al .env.');
        }

        return new WebPush(['VAPID' => [
            'subject' => $vapid['subject'],
            'publicKey' => $vapid['public_key'],
            'privateKey' => $vapid['private_key'],
        ]]);
    }
}
