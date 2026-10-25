<?php

namespace App\Http\Controllers;

use App\Models\Caretaker;
use App\Models\Family;
use App\Support\PinThrottle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function create(?string $slug = null): Response
    {
        $family = $this->resolveFamily($slug);

        return Inertia::render('Entrar', [
            'needsFamily' => ! $family,
            'family' => $family?->only(['name', 'slug']),
            'caretakers' => $family
                ? $family->caretakers()->where('is_active', true)->orderBy('name')
                    ->get(['id', 'name', 'type', 'badge_color'])
                : [],
        ]);
    }

    public function store(Request $request, PinThrottle $throttle): RedirectResponse
    {
        $data = $request->validate([
            'caretaker_id' => ['required', 'integer'],
            'pin' => ['required', 'string', 'max:20'],
        ]);

        $caretaker = Caretaker::where('is_active', true)->find($data['caretaker_id']);

        $throttle->ensureNotLocked($request, (int) $data['caretaker_id']);

        // Mismo mensaje exista o no el cuidador, para no revelar quién existe.
        if (! $caretaker || ! Hash::check($data['pin'], $caretaker->getAuthPassword())) {
            $throttle->fail($request, (int) $data['caretaker_id']);

            throw ValidationException::withMessages(['pin' => trans('ui.auth.invalid')]);
        }

        $throttle->clear($request, $caretaker->id);

        $request->session()->regenerate();
        $request->session()->forget('baby_id');
        Auth::login($caretaker);

        return redirect()->intended(route('inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $slug = $request->user()?->family?->slug;

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login', $slug ? ['slug' => $slug] : []);
    }

    private function resolveFamily(?string $slug): ?Family
    {
        if ($slug) {
            return Family::where('slug', $slug)->where('is_active', true)->firstOrFail();
        }

        $families = Family::where('is_active', true)->limit(2)->get();

        return $families->count() === 1 ? $families->first() : null;
    }
}
