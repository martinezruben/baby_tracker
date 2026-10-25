<?php

namespace App\Http\Controllers;

use App\Http\Requests\PinRules;
use App\Support\PinThrottle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CaretakerPinController extends Controller
{
    public function update(Request $request, PinThrottle $throttle): RedirectResponse
    {
        $caretaker = $request->user();

        $data = $request->validate([
            'current_pin' => ['required', 'string', 'max:20'],
            'pin' => PinRules::newPin(),
        ], trans('ui.validation'), trans('ui.fields'));

        $throttle->ensureNotLocked($request, $caretaker->id, 'current_pin');

        if (! Hash::check($data['current_pin'], $caretaker->getAuthPassword())) {
            $throttle->fail($request, $caretaker->id);

            throw ValidationException::withMessages(['current_pin' => trans('ui.auth.invalid')]);
        }

        $throttle->clear($request, $caretaker->id);
        $caretaker->update(['pin' => $data['pin']]);

        return back()->with('status', trans('ui.auth.pin_changed'));
    }
}
