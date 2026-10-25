<?php

namespace App\Support;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Limita los intentos de PIN por cuidador y dispositivo para frenar la fuerza bruta. */
class PinThrottle
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public function __construct(private RateLimiter $limiter) {}

    public function ensureNotLocked(Request $request, int $caretakerId, string $field = 'pin'): void
    {
        $key = $this->key($request, $caretakerId);

        if ($this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = $this->limiter->availableIn($key);

            throw ValidationException::withMessages([
                $field => trans('ui.auth.locked', ['minutes' => (int) ceil($seconds / 60)]),
            ]);
        }
    }

    public function fail(Request $request, int $caretakerId): void
    {
        $this->limiter->hit($this->key($request, $caretakerId), self::DECAY_SECONDS);
    }

    public function clear(Request $request, int $caretakerId): void
    {
        $this->limiter->clear($this->key($request, $caretakerId));
    }

    private function key(Request $request, int $caretakerId): string
    {
        return 'pin:'.$caretakerId.'|'.$request->ip();
    }
}
