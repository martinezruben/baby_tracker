<?php

namespace App\Http\Controllers;

use App\Actions\SaveAppointment;
use App\Enums\AppointmentStatus;
use App\Enums\DoseStatus;
use App\Enums\PrescriptionStatus;
use App\Http\Requests\AppointmentRequest;
use App\Http\Requests\PrescriptionRequest;
use App\Http\Requests\ReminderRequest;
use App\Models\Appointment;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Support\ActiveBaby;
use App\Support\FamilyClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $baby = ActiveBaby::for($request);
        $base = Appointment::query()->where('baby_id', $baby->id)->withCount(['attachments', 'prescriptions']);

        $upcoming = (clone $base)->where('status', AppointmentStatus::Scheduled)->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')->get();

        $past = (clone $base)->whereNotIn('id', $upcoming->pluck('id'))
            ->orderByDesc('scheduled_at')->limit(30)->get();

        return Inertia::render('Citas/Index', [
            'upcoming' => $upcoming->map(fn ($a) => $this->card($a))->all(),
            'past' => $past->map(fn ($a) => $this->card($a))->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Citas/Form', $this->formProps($request));
    }

    public function store(AppointmentRequest $request, SaveAppointment $save): RedirectResponse
    {
        $appointment = $save->handle(
            $request->user(),
            ActiveBaby::for($request),
            $request->appointmentData(),
            $request->vitals(),
        );

        return redirect()->route('appointments.show', $appointment)->with('status', trans('ui.citas.saved'));
    }

    public function show(Request $request, Appointment $appointment): Response
    {
        $this->authorizeAppointment($request, $appointment);

        $appointment->load(['attachments', 'measurements', 'prescriptions.medicine', 'prescriptions.doses', 'reminderRule']);
        $family = $request->user()->family;

        return Inertia::render('Citas/Show', [
            'appointment' => $this->detail($appointment),
            'clock' => $this->clockProps($request),
            'medicines' => Medicine::where('family_id', $family->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'typical_dose', 'unit']),
            'intervals' => PrescriptionRequest::INTERVALS,
        ]);
    }

    public function edit(Request $request, Appointment $appointment): Response
    {
        $this->authorizeAppointment($request, $appointment);
        $clock = FamilyClock::for($request->user()->family);

        $vitals = $appointment->measurements()->get()->mapWithKeys(fn ($m) => [$m->type->value => (float) $m->value]);
        $rule = $appointment->reminderRule;

        return Inertia::render('Citas/Form', $this->formProps($request) + [
            'appointment' => [
                'id' => $appointment->id,
                'reason' => $appointment->reason,
                'doctor_name' => $appointment->doctor_name,
                'specialty' => $appointment->specialty,
                'location' => $appointment->location,
                'scheduled_at' => $clock->toLocal($appointment->scheduled_at),
                'status' => $appointment->status->value,
                'symptoms' => $appointment->symptoms,
                'diagnosis' => $appointment->diagnosis,
                'notes' => $appointment->notes,
                'follow_up_at' => $appointment->follow_up_at ? $clock->toLocal($appointment->follow_up_at) : null,
                'remind' => $rule ? $rule->is_active || $appointment->status !== AppointmentStatus::Scheduled : true,
                'offsets_minutes' => $rule?->offsets_minutes ?? [-1440, -60],
                'vitals' => $vitals,
            ],
        ]);
    }

    public function update(AppointmentRequest $request, Appointment $appointment, SaveAppointment $save): RedirectResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $save->handle($request->user(), $appointment->baby, $request->appointmentData(), $request->vitals(), $appointment);

        return redirect()->route('appointments.show', $appointment)->with('status', trans('ui.citas.saved'));
    }

    public function destroy(Request $request, Appointment $appointment, SaveAppointment $save): RedirectResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $save->delete($appointment);

        return redirect()->route('appointments.index')->with('status', trans('ui.citas.deleted'));
    }

    private function authorizeAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless($appointment->family_id === $request->user()->family_id, 404);
    }

    private function clockProps(Request $request): array
    {
        $clock = FamilyClock::for($request->user()->family);

        return ['now' => $clock->now()->format(FamilyClock::LOCAL_FORMAT), 'timezone' => $clock->timezone];
    }

    private function formProps(Request $request): array
    {
        $baby = ActiveBaby::for($request);
        $clock = FamilyClock::for($request->user()->family);

        return [
            'baby' => $baby->only(['id', 'first_name']),
            'clock' => $this->clockProps($request),
            'defaults' => ['scheduled_at' => $clock->now()->addDay()->setTime(9, 0)->format(FamilyClock::LOCAL_FORMAT)],
            'units' => $request->user()->family->settings->only(['weight_unit', 'height_unit', 'temperature_unit']),
            'options' => ['offsets' => ReminderRequest::OFFSETS],
        ];
    }

    private function card(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'reason' => $appointment->reason,
            'doctor_name' => $appointment->doctor_name,
            'location' => $appointment->location,
            'scheduled_at' => $appointment->scheduled_at->toIso8601String(),
            'status' => $appointment->status->value,
            'files_count' => $appointment->attachments_count,
            'meds_count' => $appointment->prescriptions_count,
        ];
    }

    private function detail(Appointment $appointment): array
    {
        return $this->card($appointment->loadCount(['attachments', 'prescriptions'])) + [
            'specialty' => $appointment->specialty,
            'symptoms' => $appointment->symptoms,
            'diagnosis' => $appointment->diagnosis,
            'notes' => $appointment->notes,
            'follow_up_at' => $appointment->follow_up_at?->toIso8601String(),
            'vitals' => $appointment->measurements->map(fn ($m) => ['type' => $m->type->value, 'value' => (float) $m->value, 'unit' => $m->unit])->values(),
            'reminder' => $appointment->reminderRule?->is_active ? ['offsets' => $appointment->reminderRule->offsets_minutes] : null,
            'files' => $appointment->attachments->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->original_name,
                'mime' => $f->mime,
                'size' => $f->size,
                'is_image' => $f->isImage(),
                'url' => route('appointments.files.show', [$appointment, $f]),
            ])->values(),
            'prescriptions' => $appointment->prescriptions->map(fn (Prescription $p) => $this->prescription($p))->values(),
        ];
    }

    private function prescription(Prescription $p): array
    {
        $doses = $p->doses;

        return [
            'id' => $p->id,
            'medicine' => $p->medicine->name,
            'dose' => (float) $p->dose,
            'unit' => $p->unit,
            'interval_hours' => $p->interval_hours,
            'total_doses' => $p->total_doses,
            'starts_at' => $p->starts_at->toIso8601String(),
            'instructions' => $p->instructions,
            'status' => $p->status->value,
            'given' => $doses->where('status', DoseStatus::Given)->count(),
            'skipped' => $doses->where('status', DoseStatus::Skipped)->count(),
            'next_at' => $p->status === PrescriptionStatus::Active
                ? $doses->firstWhere('status', DoseStatus::Pending)?->scheduled_at->toIso8601String()
                : null,
            'doses' => $doses->map(fn ($d) => [
                'id' => $d->id,
                'at' => $d->scheduled_at->toIso8601String(),
                'status' => $d->status->value,
                'given_at' => $d->given_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
