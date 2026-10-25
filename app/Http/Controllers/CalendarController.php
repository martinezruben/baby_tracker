<?php

namespace App\Http\Controllers;

use App\Support\ActiveBaby;
use App\Support\CalendarData;
use App\Support\FamilyClock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $clock = FamilyClock::for($request->user()->family);
        $today = $clock->now()->toDateString();

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('mes')) ? $request->query('mes') : substr($today, 0, 7);

        return Inertia::render('Calendario', [
            'today' => $today,
            'data' => (new CalendarData($clock))->month(ActiveBaby::for($request), $month),
        ]);
    }
}
