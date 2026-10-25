<?php

namespace App\Support;

use App\Enums\BreastSide;
use App\Enums\FeedType;
use App\Models\ActiveBreastFeed;
use App\Models\Baby;
use App\Models\Caretaker;
use App\Models\FeedLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cronómetro de lactancia, uno por bebé. Guarda los segundos acumulados por lado y desde
 * qué instante corre el lado activo (ticked_at), así que sobrevive a cerrar la app y se
 * sincroniza entre dispositivos y cuidadores.
 */
class BreastFeedSession
{
    public function start(Baby $baby, Caretaker $by, BreastSide $side): ActiveBreastFeed
    {
        if (ActiveBreastFeed::where('baby_id', $baby->id)->exists()) {
            throw ValidationException::withMessages(['side' => trans('ui.registrar.breast.already_active')]);
        }

        return ActiveBreastFeed::create([
            'family_id' => $baby->family_id,
            'baby_id' => $baby->id,
            'caretaker_id' => $by->id,
            'active_side' => $side,
            'is_paused' => false,
            'started_at' => now(),
            'ticked_at' => now(),
        ]);
    }

    public function pause(ActiveBreastFeed $session): void
    {
        $this->settle($session);
        $session->update(['is_paused' => true]);
    }

    public function resume(ActiveBreastFeed $session): void
    {
        $this->settle($session);
        $session->update(['is_paused' => false]);
    }

    public function switchSide(ActiveBreastFeed $session): void
    {
        $this->settle($session);
        $session->update([
            'active_side' => $session->active_side === BreastSide::Left ? BreastSide::Right : BreastSide::Left,
            'is_paused' => false,
        ]);
    }

    /** Cierra la sesión y deja el registro de la toma. */
    public function finish(ActiveBreastFeed $session): FeedLog
    {
        return DB::transaction(function () use ($session) {
            $this->settle($session);

            $left = $session->left_seconds;
            $right = $session->right_seconds;

            $log = FeedLog::create([
                'family_id' => $session->family_id,
                'baby_id' => $session->baby_id,
                'caretaker_id' => $session->caretaker_id,
                'occurred_at' => $session->started_at,
                'started_at' => $session->started_at,
                'ended_at' => now(),
                'type' => FeedType::Breast,
                'side' => match (true) {
                    $left > 0 && $right === 0 => BreastSide::Left,
                    $right > 0 && $left === 0 => BreastSide::Right,
                    default => null, // los dos lados
                },
                'duration_seconds' => $left + $right,
                'pause_seconds' => $session->pause_seconds,
                'left_seconds' => $left,
                'right_seconds' => $right,
            ]);

            $session->delete();

            return $log;
        });
    }

    public function discard(ActiveBreastFeed $session): void
    {
        $session->delete();
    }

    /**
     * Estado para la interfaz, con los segundos ya puestos al día hasta ahora.
     *
     * @return array{side: string, paused: bool, left_seconds: int, right_seconds: int, started_at: string}
     */
    public function present(ActiveBreastFeed $session, ?CarbonInterface $now = null): array
    {
        $elapsed = $this->elapsed($session, $now ?? now());
        $running = ! $session->is_paused;

        return [
            'side' => $session->active_side->value,
            'paused' => $session->is_paused,
            'left_seconds' => $session->left_seconds + ($running && $session->active_side === BreastSide::Left ? $elapsed : 0),
            'right_seconds' => $session->right_seconds + ($running && $session->active_side === BreastSide::Right ? $elapsed : 0),
            'started_at' => $session->started_at->toIso8601String(),
        ];
    }

    /** Pasa a los acumulados lo que ha corrido desde la última acción. */
    private function settle(ActiveBreastFeed $session): void
    {
        $elapsed = $this->elapsed($session, now());

        if ($session->is_paused) {
            $session->pause_seconds += $elapsed;
        } elseif ($session->active_side === BreastSide::Left) {
            $session->left_seconds += $elapsed;
        } else {
            $session->right_seconds += $elapsed;
        }

        $session->ticked_at = now();
        $session->save();
    }

    private function elapsed(ActiveBreastFeed $session, CarbonInterface $now): int
    {
        return max(0, (int) $session->ticked_at->diffInSeconds($now, absolute: true));
    }
}
