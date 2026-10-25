<?php

namespace App\Http\Controllers;

use App\Actions\SyncVaccineReminders;
use App\Models\Baby;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public const LANGUAGES = ['es', 'en'];

    public function index(Request $request): Response
    {
        $family = $request->user()->family;

        return Inertia::render('Ajustes/Index', [
            'family_name' => $family->name,
            'counts' => [
                'caretakers' => $family->caretakers()->where('is_active', true)->count(),
                'babies' => $family->babies()->count(),
            ],
        ]);
    }

    public function preferences(Request $request): Response
    {
        $family = $request->user()->family;
        $s = $family->settings;

        return Inertia::render('Ajustes/Preferencias', [
            'prefs' => [
                'family_name' => $family->name,
                'language' => $s->language,
                'timezone' => $s->timezone,
                'time_format' => $s->time_format,
                'volume_unit' => $s->volume_unit,
                'weight_unit' => $s->weight_unit,
                'height_unit' => $s->height_unit,
                'temperature_unit' => $s->temperature_unit,
                'growth_chart_standard' => $s->growth_chart_standard,
            ],
            'languages' => self::LANGUAGES,
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function updatePreferences(Request $request, SyncVaccineReminders $sync): RedirectResponse
    {
        $data = $request->validate([
            'family_name' => ['required', 'string', 'max:80'],
            'language' => ['required', Rule::in(self::LANGUAGES)],
            'timezone' => ['required', 'timezone:all'],
            'time_format' => ['required', Rule::in(['12', '24'])],
            'volume_unit' => ['required', Rule::in(['ml', 'oz'])],
            'weight_unit' => ['required', Rule::in(['kg', 'lb'])],
            'height_unit' => ['required', Rule::in(['cm', 'in'])],
            'temperature_unit' => ['required', Rule::in(['C', 'F'])],
            'growth_chart_standard' => ['required', Rule::in(['who', 'cdc'])],
        ], trans('ui.validation'), trans('ui.ajustes.fields'));

        $family = $request->user()->family;
        $settings = $family->settings;
        $timezoneChanged = $settings->timezone !== $data['timezone'];

        $family->update(['name' => $data['family_name']]);
        $settings->update(collect($data)->except('family_name')->all());

        // Los avisos de vacunas salen a las 9:00 locales: si cambia la zona horaria, se reprograman.
        if ($timezoneChanged) {
            $family->babies()->get()->each(fn (Baby $baby) => $sync->handle($baby));
        }

        return back()->with('status', trans('ui.ajustes.saved'));
    }

    /** Idioma personal del cuidador (por defecto sigue al de la familia). */
    public function language(Request $request): RedirectResponse
    {
        $data = $request->validate(['language' => ['nullable', Rule::in(self::LANGUAGES)]]);

        $request->user()->update(['language' => $data['language'] ?? null]);

        return back();
    }
}
