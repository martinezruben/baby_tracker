<?php

namespace App\Http\Middleware;

use App\Support\UiTranslations;
use Illuminate\Http\Request;
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
        $babies = $family?->babies()->orderBy('birth_date')->get(['id', 'first_name', 'last_name', 'birth_date', 'gender']);
        $activeBaby = $babies?->firstWhere('id', $request->session()->get('baby_id')) ?? $babies?->first();

        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'locale' => $locale,
            't' => fn () => UiTranslations::for($locale),
            'auth' => [
                'caretaker' => $caretaker?->only(['id', 'name', 'role', 'badge_color']),
            ],
            'family' => $family?->only(['name', 'slug']),
            'babies' => $babies,
            'activeBabyId' => $activeBaby?->id,
            'flash' => ['status' => fn () => $request->session()->get('status')],
        ];
    }
}
