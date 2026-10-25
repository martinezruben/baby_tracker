<?php

namespace App\Http\Controllers;

use App\Enums\BreastSide;
use App\Models\ActiveBreastFeed;
use App\Support\ActiveBaby;
use App\Support\BreastFeedSession;
use App\Support\FamilyClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BreastFeedController extends Controller
{
    public function create(Request $request): Response
    {
        $baby = ActiveBaby::for($request);
        $clock = FamilyClock::for($request->user()->family);

        return Inertia::render('Registrar/Pecho', [
            'baby' => $baby->only(['id', 'first_name']),
            'clock' => ['now' => $clock->now()->format(FamilyClock::LOCAL_FORMAT), 'timezone' => $clock->timezone],
            'active' => ActiveBreastFeed::where('baby_id', $baby->id)->exists(),
        ]);
    }

    public function start(Request $request, BreastFeedSession $sessions): RedirectResponse
    {
        $data = $request->validate(['side' => ['required', Rule::enum(BreastSide::class)]], trans('ui.validation'));

        $sessions->start(ActiveBaby::for($request), $request->user(), BreastSide::from($data['side']));

        return redirect()->route('inicio');
    }

    public function update(Request $request, string $op, BreastFeedSession $sessions): RedirectResponse
    {
        abort_unless(in_array($op, ['pausar', 'reanudar', 'cambiar', 'finalizar'], true), 404);

        $session = ActiveBreastFeed::where('baby_id', ActiveBaby::for($request)->id)->firstOrFail();

        if ($op === 'finalizar') {
            $log = $sessions->finish($session);

            return back()->with('undo', [
                'message' => trans('ui.registrar.saved.pecho'),
                'url' => route('records.destroy', ['type' => 'pecho', 'id' => $log->id]),
            ]);
        }

        match ($op) {
            'pausar' => $sessions->pause($session),
            'reanudar' => $sessions->resume($session),
            'cambiar' => $sessions->switchSide($session),
        };

        return back();
    }

    public function destroy(Request $request, BreastFeedSession $sessions): RedirectResponse
    {
        $session = ActiveBreastFeed::where('baby_id', ActiveBaby::for($request)->id)->firstOrFail();
        $sessions->discard($session);

        return back();
    }
}
