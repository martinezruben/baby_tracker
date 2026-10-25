<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\FeedType;
use App\Models\ActiveBreastFeed;
use App\Models\Appointment;
use App\Models\FeedLog;
use App\Models\Medicine;
use App\Models\SleepLog;
use App\Support\ActiveBaby;
use App\Support\Activities\ActivityRegistry;
use App\Support\BreastFeedSession;
use App\Support\DashboardData;
use App\Support\FamilyClock;
use App\Support\VaccineSchedule;
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
        $activity = ActivityRegistry::findOrFail($type);
        $baby = ActiveBaby::for($request);
        $family = $request->user()->family;
        $clock = FamilyClock::for($family);

        $last = $activity->query($baby->id)->latest($activity->timeColumn())->first();

        return Inertia::render($activity->page(), [
            'baby' => $baby->only(['id', 'first_name']),
            'clock' => ['now' => $clock->now()->format(FamilyClock::LOCAL_FORMAT), 'timezone' => $clock->timezone],
            'lastAt' => $last?->{$activity->timeColumn()}?->toIso8601String(),
            'last' => $last ? $activity->summary($last) : null,
            'volumeUnit' => $family->settings->volume_unit,
            'medicines' => $type === 'medicina'
                ? Medicine::where('family_id', $family->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'typical_dose', 'unit'])
                : [],
            'openSleepId' => $type === 'sueno' ? SleepLog::where('baby_id', $baby->id)->whereNull('ended_at')->value('id') : null,
            'units' => ['weight' => $family->settings->weight_unit, 'height' => $family->settings->height_unit, 'temperature' => $family->settings->temperature_unit],
            'breastActive' => ActiveBreastFeed::where('baby_id', $baby->id)->exists(),
        ]);
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
