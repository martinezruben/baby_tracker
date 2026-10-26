<?php

namespace App\Support;

use App\Enums\OccurrenceStatus;
use App\Enums\TargetStatus;
use App\Models\Caretaker;
use App\Models\NotificationTarget;
use App\Models\PushSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/** Qué hace falta para que los avisos push lleguen y qué falta en este servidor. */
class PushDiagnostics
{
    public const HEARTBEAT = 'reminders:last_run';

    /** El despachador corre cada minuto: pasado este tiempo sin señal, el cron no está funcionando. */
    public const STALE_MINUTES = 3;

    /** @return array<string, mixed> */
    public function report(?Caretaker $me = null): array
    {
        $public = (string) config('webpush.vapid.public_key');
        $private = (string) config('webpush.vapid.private_key');
        $last = Cache::get(self::HEARTBEAT);

        return [
            'vapid_configured' => $public !== '' && $private !== '',
            'vapid_valid' => $this->validPublicKey($public),
            'secure' => str_starts_with((string) config('app.url'), 'https://'),
            'extensions' => collect(['openssl', 'curl', 'mbstring'])->mapWithKeys(fn ($e) => [$e => extension_loaded($e)])->all(),
            'dispatcher_last_run' => $last ? CarbonImmutable::parse($last)->toIso8601String() : null,
            'dispatcher_ok' => $last && CarbonImmutable::parse($last)->greaterThan(now()->subMinutes(self::STALE_MINUTES)),
            'overdue' => NotificationTarget::where('status', TargetStatus::Pending)
                ->where('next_send_at', '<=', now()->subMinutes(self::STALE_MINUTES))
                ->whereHas('occurrence', fn ($q) => $q->where('status', OccurrenceStatus::Active))->count(),
            'devices_total' => PushSubscription::count(),
            'devices_mine' => $me ? $me->pushSubscriptions()->count() : 0,
        ];
    }

    /** Una clave pública VAPID es un punto P-256 sin comprimir: 65 bytes que empiezan por 0x04. */
    public function validPublicKey(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        $raw = base64_decode(strtr($key, '-_', '+/').str_repeat('=', (4 - strlen($key) % 4) % 4), true);

        return $raw !== false && strlen($raw) === 65 && $raw[0] === "\x04";
    }

    public function beat(): void
    {
        Cache::forever(self::HEARTBEAT, now()->toIso8601String());
    }
}
