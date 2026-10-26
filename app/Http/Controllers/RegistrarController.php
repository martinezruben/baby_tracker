<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\FeedType;
use App\Models\ActiveBreastFeed;
use App\Models\Appointment;
use App\Models\BabyAllergen;
use App\Models\FeedLog;
use App\Models\Food;
use App\Models\Medicine;
use App\Models\MedicineLog;
use App\Models\SleepLog;
use App\Support\ActiveBaby;
use App\Support\Activities\ActivityRegistry;
use App\Support\Activities\ActivityType;
use App\Support\BreastFeedSession;
use App\Support\DashboardData;
use App\Support\FamilyClock;
use App\Support\VaccineSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegistrarController extends Controller
{
    /** Centro de registro: sesiones activas, "repetir lo último" y cuándo fue la última vez de cada cosa. */
    public function hub(Request $request): Response
    {
        $baby = ActiveBaby::for($request);
        $clock = FamilyClock::for($request->user()->family);
        $data = new DashboardData($clock, app(BreastFeedSession::class));

        $last = collect(ActivityRegistry::all())->map(function ($type) use ($baby) {
            $log = $type->query($baby->id)->latest($type->timeColumn())->first();

            return $log ? [
                'type' => $type->slug(),
                'at' => $log->{$type->timeColumn()}->toIso8601String(),
                'summary' => $type->summary($log),
                'repeatable' => $type->repeatable(),
            ] : null;
        })->filter()->values();

        $lastBreast = FeedLog::where('baby_id', $baby->id)->where('type', FeedType::Breast)->latest('occurred_at')->first();

        return Inertia::render('Registrar/Index', [
            'baby' => $baby->only(['id', 'first_name']),
            'clock' => ['now' => $clock->now()->format(FamilyClock::LOCAL_FORMAT), 'timezone' => $clock->timezone],
            'sessions' => $data->sessions($baby),
            'last' => $last,
            'lastBreastAt' => $lastBreast?->occurred_at?->toIso8601String(),
            'nextVaccine' => $this->nextVaccine($baby),
            'nextAppointmentAt' => Appointment::where('baby_id', $baby->id)->where('status', AppointmentStatus::Scheduled)
                ->where('scheduled_at', '>', now())->orderBy('scheduled_at')->value('scheduled_at')?->toIso8601String(),
        ]);
    }

    public function create(Request $request, string $type): Response
    {
        return $this->form($request, ActivityRegistry::findOrFail($type));
    }

    /** El mismo formulario del registro, con los valores guardados, para corregirlo. */
    public function edit(Request $request, string $type, int $id): Response
    {
        $activity = ActivityRegistry::editable($type);
        $log = $activity->query(ActiveBaby::for($request)->id)->findOrFail($id);
        $clock = FamilyClock::for($request->user()->family);

        return $this->form($request, $activity, ['id' => $log->id] + $activity->formValues($log, $clock));
    }

    private function form(Request $request, ActivityType $activity, ?array $record = null): Response
    {
        $type = $activity->slug();
        $baby = ActiveBaby::for($request);
        $family = $request->user()->family;
        $clock = FamilyClock::for($family);

        $last = $activity->query($baby->id)->latest($activity->timeColumn())->first();

        return Inertia::render($activity->page(), [
            'record' => $record,
            'baby' => $baby->only(['id', 'first_name']),
            'clock' => ['now' => $clock->now()->format(FamilyClock::LOCAL_FORMAT), 'timezone' => $clock->timezone],
            'lastAt' => $last?->{$activity->timeColumn()}?->toIso8601String(),
            'last' => $last ? $activity->summary($last) : null,
            'volumeUnit' => $family->settings->volume_unit,
            'medicines' => $type === 'medicina'
                ? $this->medicinesWithLastDose($family->id, $baby->id)
                : [],
            'allergies' => in_array($type, ['solidos', 'medicina'], true)
                ? BabyAllergen::where('baby_id', $baby->id)->whereIn('type', $type === 'solidos' ? ['food'] : ['medicine'])->pluck('name')->all()
                : [],
            'foods' => $type === 'solidos' ? Food::where('family_id', $family->id)->orderBy('name')->get(['id', 'name']) : [],
            'openSleepId' => $type === 'sueno' ? SleepLog::where('baby_id', $baby->id)->whereNull('ended_at')->value('id') : null,
            'units' => ['weight' => $family->settings->weight_unit, 'height' => $family->settings->height_unit, 'temperature' => $family->settings->temperature_unit],
            'breastActive' => ActiveBreastFeed::where('baby_id', $baby->id)->exists(),
        ]);
    }

    /** Medicinas activas con su intervalo mínimo y cuándo se dio la última dosis a este bebé (para avisar si es pronto). */
    private function medicinesWithLastDose(int $familyId, int $babyId)
    {
        $last = MedicineLog::where('baby_id', $babyId)->selectRaw('medicine_id, max(occurred_at) as last_at')->groupBy('medicine_id')->pluck('last_at', 'medicine_id');

        return Medicine::where('family_id', $familyId)->where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'typical_dose', 'unit', 'min_interval_minutes'])
            ->map(fn (Medicine $m) => $m->toArray() + ['last_given_at' => isset($last[$m->id]) ? CarbonImmutable::parse($last[$m->id], 'UTC')->toIso8601String() : null]);
    }

    /** @return array{name: string, due_on: string, overdue: bool}|null */
    private function nextVaccine($baby): ?array
    {
        $row = VaccineSchedule::for($baby)->rows($baby)->where('status', '!=', 'applied')->sortBy('days_left')->first();

        return $row ? [
            'name' => trim($row['dose']->vaccine_name.' '.$row['dose']->dose_label),
            'due_on' => $row['due_on']->toDateString(),
            'overdue' => $row['status'] === 'overdue',
        ] : null;
    }
}
