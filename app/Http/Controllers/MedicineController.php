<?php

namespace App\Http\Controllers;

use App\Actions\SavePrescription;
use App\Enums\DoseStatus;
use App\Enums\PrescriptionStatus;
use App\Http\Requests\PrescriptionRequest;
use App\Models\Medicine;
use App\Models\MedicineLog;
use App\Models\Prescription;
use App\Support\ActiveBaby;
use App\Support\FamilyClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Medicinas: tratamientos en curso, medicinas de la familia y últimas dosis. */
class MedicineController extends Controller
{
    public function index(Request $request): Response
    {
        $baby = ActiveBaby::for($request);
        $family = $request->user()->family;
        $clock = FamilyClock::for($family);

        $treatments = Prescription::where('baby_id', $baby->id)->where('status', PrescriptionStatus::Active)
            ->with(['medicine:id,name', 'doses', 'appointment:id,reason'])->orderBy('starts_at')->get()
            ->map(function (Prescription $p) {
                $next = $p->doses->firstWhere('status', DoseStatus::Pending);

                return [
                    'id' => $p->id,
                    'medicine' => $p->medicine->name,
                    'dose' => (float) $p->dose,
                    'unit' => $p->unit,
                    'interval_hours' => $p->interval_hours,
                    'total_doses' => $p->total_doses,
                    'given' => $p->doses->where('status', DoseStatus::Given)->count(),
                    'instructions' => $p->instructions,
                    'next' => $next ? ['id' => $next->id, 'at' => $next->scheduled_at->toIso8601String()] : null,
                    'appointment' => $p->appointment ? ['id' => $p->appointment->id, 'reason' => $p->appointment->reason] : null,
                ];
            })->values();

        $lastGiven = MedicineLog::where('baby_id', $baby->id)->selectRaw('medicine_id, max(occurred_at) as last_at')->groupBy('medicine_id')->pluck('last_at', 'medicine_id');

        $medicines = Medicine::where('family_id', $family->id)->orderByDesc('is_active')->orderBy('name')->get()
            ->map(fn (Medicine $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'typical_dose' => $m->typical_dose === null ? null : (float) $m->typical_dose,
                'unit' => $m->unit,
                'min_interval_hours' => $m->min_interval_minutes ? round($m->min_interval_minutes / 60, 1) : null,
                'is_supplement' => $m->is_supplement,
                'is_active' => $m->is_active,
                'notes' => $m->notes,
                'last_given_at' => isset($lastGiven[$m->id]) ? CarbonImmutable::parse($lastGiven[$m->id], 'UTC')->toIso8601String() : null,
            ])->values();

        $history = MedicineLog::where('baby_id', $baby->id)->with(['medicine:id,name', 'caretaker:id,name'])
            ->orderByDesc('occurred_at')->limit(15)->get()
            ->map(fn (MedicineLog $l) => [
                'id' => $l->id,
                'name' => $l->medicine?->name,
                'dose' => (float) $l->dose,
                'unit' => $l->unit,
                'at' => $l->occurred_at->toIso8601String(),
                'by' => $l->caretaker?->name,
            ])->values();

        return Inertia::render('Medicinas', [
            'baby' => $baby->only(['id', 'first_name']),
            'clock' => ['now' => $clock->now()->format(FamilyClock::LOCAL_FORMAT), 'timezone' => $clock->timezone],
            'treatments' => $treatments,
            'medicines' => $medicines,
            'history' => $history,
            'intervals' => PrescriptionRequest::INTERVALS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Medicine::create($this->validated($request) + ['family_id' => $request->user()->family_id]);

        return back()->with('status', trans('ui.medicinas.saved'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->find($request, $id)->update($this->validated($request, $id));

        return back()->with('status', trans('ui.medicinas.saved'));
    }

    /** Archivar: deja de ofrecerse en los formularios pero conserva su historial. */
    public function toggle(Request $request, int $id): RedirectResponse
    {
        $medicine = $this->find($request, $id);
        $medicine->update(['is_active' => ! $medicine->is_active]);

        return back()->with('status', trans($medicine->is_active ? 'ui.medicinas.restored' : 'ui.medicinas.archived'));
    }

    /** Tratamiento sin cita (la cita no es obligatoria para recetar). */
    public function storeTreatment(PrescriptionRequest $request, SavePrescription $save): RedirectResponse
    {
        $save->handle($request->user(), ActiveBaby::for($request), null, $request->prescriptionData());

        return back()->with('status', trans('ui.citas.prescription_saved'));
    }

    private function find(Request $request, int $id): Medicine
    {
        return Medicine::where('family_id', $request->user()->family_id)->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignore = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('medicines', 'name')->where('family_id', $request->user()->family_id)->whereNull('deleted_at')->ignore($ignore),
            ],
            'typical_dose' => ['nullable', 'numeric', 'between:0.01,10000'],
            'unit' => ['nullable', 'string', 'max:8'],
            'min_interval_hours' => ['nullable', 'numeric', 'between:0.5,72'],
            'is_supplement' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], trans('ui.validation') + ['name.unique' => trans('ui.medicinas.duplicate')], trans('ui.medicinas.fields'));

        return [
            'name' => trim($data['name']),
            'typical_dose' => $data['typical_dose'] ?? null,
            'unit' => $data['unit'] ?? null,
            'min_interval_minutes' => isset($data['min_interval_hours']) ? (int) round($data['min_interval_hours'] * 60) : null,
            'is_supplement' => $data['is_supplement'] ?? false,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
