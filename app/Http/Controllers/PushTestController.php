<?php

namespace App\Http\Controllers;

use App\Actions\SaveReminder;
use App\Services\Notifications\Channels\WebPushChannel;
use App\Support\PushDiagnostics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** Pruebas de los avisos push: inmediata, programada a 1 minuto (recorre cron + envío + botones) y diagnóstico. */
class PushTestController extends Controller
{
    public function __invoke(Request $request, WebPushChannel $channel): JsonResponse
    {
        try {
            $channel->sendTest($request->user());
        } catch (Throwable $e) {
            report($e);

            // El motivo real (suscripción caducada, claves VAPID, red…) solo lo ve un administrador.
            return response()->json([
                'message' => trans('ui.push.test_failed'),
                'detail' => $request->user()->isAdmin() ? $e->getMessage() : null,
            ], 422);
        }

        return response()->json(['message' => trans('ui.push.test_sent')]);
    }

    /**
     * Recordatorio real que vence en un minuto, solo para quien lo pide, que insiste hasta confirmar.
     * Prueba todo el camino: cron, despachador, envío y los botones Confirmar / Posponer.
     */
    public function scheduled(Request $request, SaveReminder $save): JsonResponse
    {
        $user = $request->user();

        $save->handle($user, [
            'title' => trans('ui.push.scheduled_title'),
            'body' => trans('ui.push.scheduled_body'),
            'event_at' => now()->addMinute()->startOfMinute(),
            'baby_id' => null,
            'offsets_minutes' => [0],
            'requires_ack' => true,
            'repeat_every_minutes' => 5,
            'max_repeats' => null,
            'ack_scope' => 'any',
            'snooze_options' => [5, 10],
            'respect_quiet_hours' => false,
        ], [$user->id]);

        return response()->json(['message' => trans('ui.push.scheduled_ok')]);
    }

    public function status(Request $request, PushDiagnostics $diagnostics): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json($diagnostics->report($request->user()));
    }
}
