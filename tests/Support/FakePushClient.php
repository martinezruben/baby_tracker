<?php

namespace Tests\Support;

use App\Models\PushSubscription;
use App\Services\Notifications\Push\PushClient;
use App\Services\Notifications\Push\PushResult;

class FakePushClient implements PushClient
{
    /** @var array<int, array{endpoint: string, payload: string}> */
    public array $sent = [];

    public array $expired = [];

    public array $failing = [];

    public function send(PushSubscription $subscription, string $payload): PushResult
    {
        if (in_array($subscription->endpoint, $this->expired, true)) {
            return new PushResult(false, expired: true, reason: 'Gone');
        }

        if (in_array($subscription->endpoint, $this->failing, true)) {
            return new PushResult(false, reason: 'Server error');
        }

        $this->sent[] = ['endpoint' => $subscription->endpoint, 'payload' => $payload];

        return new PushResult(true);
    }
}
