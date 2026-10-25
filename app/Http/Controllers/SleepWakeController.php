<?php

namespace App\Http\Controllers;

use App\Models\SleepLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SleepWakeController extends Controller
{
    /** "Despertó": cierra el sueño en curso con la hora actual. */
    public function __invoke(Request $request, SleepLog $log): RedirectResponse
    {
        abort_unless($log->family_id === $request->user()->family_id && $log->ended_at === null, 404);

        $log->update([
            'ended_at' => now(),
            'duration_minutes' => (int) $log->started_at->diffInMinutes(now()),
        ]);

        return back()->with('undo', [
            'message' => trans('ui.registrar.saved.sueno'),
            'url' => route('sleep.reopen', $log),
            'method' => 'patch', // deshacer "despertó" = volver a dejarlo durmiendo
        ]);
    }

    public function reopen(Request $request, SleepLog $log): RedirectResponse
    {
        abort_unless($log->family_id === $request->user()->family_id, 404);

        $log->update(['ended_at' => null, 'duration_minutes' => null]);

        return back()->with('status', trans('ui.registrar.undone'));
    }
}
