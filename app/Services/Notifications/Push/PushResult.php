<?php

namespace App\Services\Notifications\Push;

final readonly class PushResult
{
    public function __construct(
        public bool $delivered,
        public bool $expired = false,
        public ?string $reason = null,
    ) {}
}
