<?php

namespace App\Http\Controllers;

use App\Actions\SyncVaccineReminders;
use App\Models\Baby;
use App\Models\Vaccination;
use App\Models\VaccineDose;
use App\Support\ActiveBaby;
use App\Support\FamilyClock;
use App\Support\VaccineSchedule;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VaccineController extends Controller
{
    public function index(Request $request): Response
    {
        $baby = ActiveBaby::for($request);
        $family = $request->user()->family;
        $rows = VaccineSchedule::for($baby)->rows($baby);

        $row = fn (array $r) => [
            'dose_id' => $r['dose']->id,
            'name' => $r['dose']->vaccine_name,
            'label' => $r['dose']->dose_label,
            'notes' => $r['dose']->notes,
            'age_days' => $r['dose']->age_days,
            'due_on' => $r['due_on']->toDateString(),
            'days_left' => $r['days_left'],
            'status' => $r['status'],
            'vaccination' => $r['vaccination'] ? $this->vaccinationData($r['vaccination']) : null,
        ];

        return Inertia::render('Vacunas/Index', [
            'schedule_name' => $family->settings->vaccine_schedule_name,
            'has_schedule' => $rows->isNotEmpty(),
            'counts' => [
                'total' => $rows->count(),
                'applied' => $rows->where('status', 'applied')->count(),
                'overdue' => $rows->where('status', 'overdue')->count(),
            ],
            'doses' => $rows->map($row)->all(),
            'extra' => $baby->vaccinations()->whereNull('vaccine_dose_id')->orderByDesc('administered_at')->get()
                ->map(fn ($v) => $this->vaccinationData($v))->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $baby = ActiveBaby::for($request);
        $clock = FamilyClock::for($request->user()->family);

        $pending = VaccineSchedule::for($baby)->rows($baby)->where('status', '!=', 'applied')->map(fn ($r) => [
            'id' => $r['dose']->id,
            'name' => $r['dose']->vaccine_name,
            'label' => $r['dose']->dose_label,
            'status' => $r['status'],
        ])->values();

        return Inertia::render('Vacunas/Form', [
            'baby' => $baby->only(['id', 'first_name']),
            'clock' => ['now' => $clock->now()->format(FamilyClock::LOCAL_FORMAT), 'timezone' => $clock->timezone],
            'pending' => $pending,
            'selected' => $request->integer('dosis') ?: null,
        ]);
    }

    public function store(Request $request, SyncVaccineReminders $sync): RedirectResponse
    {
        $baby = ActiveBaby::for($request);
        $clock = FamilyClock::for($request->user()->family);

        $data = $request->validate([
            'vaccine_dose_id' => ['nullable', Rule::exists(VaccineDose::class, 'id')->where('family_id', $baby->family_id), $this->notAppliedYet($baby)],
            'vaccine_name' => ['nullable', 'required_without:vaccine_dose_id', 'string', 'max:80'],
            'dose_label' => ['nullable', 'string', 'max:40'],
            'administered_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
                function (string $attribute, mixed $value, Closure $fail) use ($clock) {
                    try {
                        $at = $clock->parse((string) $value);
                    } catch (\Throwable) {
                        return;
                    }
                    if ($at->greaterThan(now()->addMinutes(5))) {
                        $fail(trans('ui.validation.not_future'));
                    }
                },
            ],
            'lot' => ['nullable', 'string', 'max:60'],
            'place' => ['nullable', 'string', 'max:120'],
            'administered_by' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], trans('ui.validation'), trans('ui.vacunas.fields'));

        $dose = ! empty($data['vaccine_dose_id']) ? VaccineDose::find($data['vaccine_dose_id']) : null;

        $vaccination = Vaccination::create([
            'family_id' => $baby->family_id,
            'baby_id' => $baby->id,
            'caretaker_id' => $request->user()->id,
            'vaccine_dose_id' => $dose?->id,
            'vaccine_name' => $dose?->vaccine_name ?? trim($data['vaccine_name']),
            'dose_label' => $dose ? $dose->dose_label : ($data['dose_label'] ?? null),
            'administered_at' => $clock->parse($data['administered_at']),
            'lot' => $data['lot'] ?? null,
            'place' => $data['place'] ?? null,
            'administered_by' => $data['administered_by'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $sync->handle($baby);

        return redirect()->route('vaccines.index')->with('undo', [
            'message' => trans('ui.vacunas.saved'),
            'url' => route('vaccines.destroy', $vaccination),
            'method' => 'delete',
        ]);
    }

    public function destroy(Request $request, Vaccination $vaccination, SyncVaccineReminders $sync): RedirectResponse
    {
        abort_unless($vaccination->family_id === $request->user()->family_id, 404);

        $vaccination->delete();
        $sync->handle($vaccination->baby);

        return redirect()->route('vaccines.index')->with('undo', [
            'message' => trans('ui.vacunas.deleted'),
            'url' => route('vaccines.restore', $vaccination->id),
            'method' => 'post',
        ]);
    }

    public function restore(Request $request, int $id, SyncVaccineReminders $sync): RedirectResponse
    {
        $vaccination = Vaccination::onlyTrashed()->where('family_id', $request->user()->family_id)->findOrFail($id);
        $vaccination->restore();
        $sync->handle($vaccination->baby);

        return redirect()->route('vaccines.index')->with('status', trans('ui.vacunas.restored'));
    }

    /** Una dosis del esquema solo se aplica una vez a cada bebé. */
    private function notAppliedYet(Baby $baby): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($baby) {
            if ($baby->vaccinations()->where('vaccine_dose_id', $value)->exists()) {
                $fail(trans('ui.vacunas.errors.already_applied'));
            }
        };
    }

    /** @return array<string, mixed> */
    private function vaccinationData(Vaccination $v): array
    {
        return [
            'id' => $v->id,
            'name' => $v->vaccine_name,
            'label' => $v->dose_label,
            'administered_at' => $v->administered_at->toIso8601String(),
            'lot' => $v->lot,
            'place' => $v->place,
            'administered_by' => $v->administered_by,
            'notes' => $v->notes,
        ];
    }
}
