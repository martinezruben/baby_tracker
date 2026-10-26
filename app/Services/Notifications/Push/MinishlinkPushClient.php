<?php

namespace App\Services\Notifications\Push;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

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

    /**
     * Sin logger, la librería avisa con E_USER_NOTICE cuando falta la extensión GMP o BCMath (solo es
     * una recomendación de velocidad) y Laravel convierte ese aviso en una excepción: el envío fallaría.
     * Se descartan los avisos informativos y el resto va al registro normal.
     */
    private function logger(): LoggerInterface
    {
        return new class(app('log')) extends AbstractLogger
        {
            public function __construct(private LoggerInterface $inner) {}

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if (in_array($level, [LogLevel::DEBUG, LogLevel::INFO, LogLevel::NOTICE], true)) {
                    return;
                }

                $this->inner->log($level, $message, $context);
            }
        };
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
        ]], logger: $this->logger());
    }
}
