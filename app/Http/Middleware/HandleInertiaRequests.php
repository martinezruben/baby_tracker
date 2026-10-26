<?php

namespace App\Http\Middleware;

use App\Support\ActiveBaby;
use App\Support\UiTranslations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $caretaker = $request->user();
        $family = $caretaker?->family;
        // Los bebés se cargan una vez por petición y se reutilizan en los controladores (ver ActiveBaby::all).
        $babies = $caretaker ? ActiveBaby::all($request)->map->only(['id', 'first_name', 'last_name', 'birth_date', 'gender'])->values() : null;
        $activeBaby = $caretaker && $babies->isNotEmpty() ? ActiveBaby::for($request) : null;

        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'locale' => $locale,
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'timezone' => $family?->settings?->timezone ?? config('app.timezone'),
            'timeFormat' => $family?->settings?->time_format ?? '12',
            // ~43 KB que no cambian entre pantallas: se envían en la primera carga y el navegador los recuerda
            // (Inertia `once`); la clave incluye el idioma, así que al cambiarlo se vuelven a pedir.
            't' => Inertia::once(fn () => UiTranslations::for($locale))->as("t.{$locale}"),
            'auth' => [
                'caretaker' => $caretaker?->only(['id', 'name', 'role', 'badge_color']),
            ],
            'family' => $family?->only(['name', 'slug']),
            'babies' => $babies,
            'activeBabyId' => $activeBaby?->id,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'undo' => fn () => $request->session()->get('undo'),
                'restored' => fn () => $request->session()->get('restored'),
            ],
        ];
    }
}
