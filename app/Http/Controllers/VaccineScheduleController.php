<?php

namespace App\Http\Controllers;

use App\Actions\SyncVaccineReminders;
use App\Models\VaccineDose;
use App\Support\VaccineScheduleImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VaccineScheduleController extends Controller
{
    public function show(Request $request): Response
    {
        $family = $request->user()->family;

        return Inertia::render('Vacunas/Esquema', [
            'name' => $family->settings->vaccine_schedule_name,
            'doses' => VaccineDose::where('family_id', $family->id)->orderBy('age_days')->orderBy('vaccine_name')->orderBy('id')->get()
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->vaccine_name, 'label' => $d->dose_label, 'age_days' => $d->age_days, 'notes' => $d->notes])->all(),
        ]);
    }

    public function name(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']], trans('ui.validation'), trans('ui.vacunas.fields'));

        $request->user()->family->settings->update(['vaccine_schedule_name' => $data['name']]);

        return back()->with('status', trans('ui.vacunas.schedule.saved'));
    }

    public function store(Request $request, SyncVaccineReminders $sync): RedirectResponse
    {
        $data = $request->validate([
            'vaccine_name' => ['required', 'string', 'max:80'],
            'dose_label' => ['nullable', 'string', 'max:40'],
            'age_months' => ['required', 'numeric', 'between:0,300'],
            'notes' => ['nullable', 'string', 'max:200'],
        ], trans('ui.validation'), trans('ui.vacunas.fields'));

        VaccineDose::create([
            'family_id' => $request->user()->family_id,
            'vaccine_name' => trim($data['vaccine_name']),
            'dose_label' => $data['dose_label'] ?? null,
            'age_days' => (int) round($data['age_months'] * 30.4375),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncFamily($request, $sync);

        return back()->with('status', trans('ui.vacunas.schedule.dose_added'));
    }

    public function destroy(Request $request, VaccineDose $dose, SyncVaccineReminders $sync): RedirectResponse
    {
        abort_unless($dose->family_id === $request->user()->family_id, 404);

        $dose->delete();
        $this->syncFamily($request, $sync);

        return back()->with('status', trans('ui.vacunas.schedule.dose_deleted'));
    }

    /** Pega el esquema de la cartilla: todo o nada, para no dejar un esquema a medias. */
    public function import(Request $request, SyncVaccineReminders $sync): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:20000'],
            'replace' => ['required', 'boolean'],
        ], trans('ui.validation'), trans('ui.vacunas.fields'));

        $parsed = VaccineScheduleImport::parse($data['text']);

        if ($parsed['errors']) {
            $first = collect($parsed['errors'])->take(5)->map(
                fn ($e) => trans('ui.vacunas.schedule.import_error', ['line' => $e['line'], 'reason' => trans("ui.vacunas.schedule.import_reason_{$e['reason']}")]),
            )->implode(' ');

            throw ValidationException::withMessages(['text' => $first]);
        }

        if ($parsed['rows'] === []) {
            throw ValidationException::withMessages(['text' => trans('ui.vacunas.schedule.import_empty')]);
        }

        DB::transaction(function () use ($request, $data, $parsed) {
            $familyId = $request->user()->family_id;

            if ($data['replace']) {
                VaccineDose::where('family_id', $familyId)->delete();
            }

            foreach ($parsed['rows'] as $row) {
                VaccineDose::create($row + ['family_id' => $familyId]);
            }
        });

        $this->syncFamily($request, $sync);

        return back()->with('status', trans_choice('ui.vacunas.schedule.imported', count($parsed['rows']), ['n' => count($parsed['rows'])]));
    }

    private function syncFamily(Request $request, SyncVaccineReminders $sync): void
    {
        $request->user()->family->babies()->get()->each(fn ($baby) => $sync->handle($baby));
    }
}
