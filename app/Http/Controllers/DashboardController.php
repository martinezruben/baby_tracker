<?php

namespace App\Http\Controllers;

use App\Support\ActiveBaby;
use App\Support\BreastFeedSession;
use App\Support\DashboardData;
use App\Support\FamilyClock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $range = in_array($request->query('rango'), ['hoy', 'semana', 'mes'], true) ? $request->query('rango') : 'hoy';

        $baby = ActiveBaby::for($request);
        $clock = FamilyClock::for($request->user()->family);
        $data = new DashboardData($clock, app(BreastFeedSession::class));

        $today = $data->summary($baby, 'hoy');

        return Inertia::render('Inicio', [
            'baby' => $baby->only(['id', 'first_name', 'birth_date']),
            'clock' => ['now' => $clock->now()->format(FamilyClock::LOCAL_FORMAT), 'timezone' => $clock->timezone],
            'today' => ['feeds' => $today['feed']['count'], 'diapers' => $today['diaper'], 'sleep_minutes' => $today['sleep']['minutes'], 'total' => $today['total']],
            'summary' => $range === 'hoy' ? $today : $data->summary($baby, $range),
            'sessions' => $data->sessions($baby),
            'upcoming' => $data->upcoming($baby),
        ]);
    }
}
