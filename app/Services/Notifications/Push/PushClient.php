<?php

namespace App\Services\Notifications\Push;

use App\Models\PushSubscription;

interface PushClient
{
    public function send(PushSubscription $subscription, string $payload): PushResult;
}
