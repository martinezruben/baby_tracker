<?php

namespace App\Http\Controllers;

use App\Models\ActiveBreastFeed;
use App\Models\DiaperLog;
use App\Models\FeedLog;
use App\Models\SleepLog;
use App\Support\ActiveBaby;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Modo nursery: pantalla de madrugada con el reloj grande y los cuatro registros más usados. */
class NurseryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $baby = ActiveBaby::for($request);
        $sleep = SleepLog::where('baby_id', $baby->id)->whereNull('ended_at')->latest('started_at')->first();

        return Inertia::render('Nursery', [
            'baby' => $baby->only(['id', 'first_name']),
            'lastFeedAt' => FeedLog::where('baby_id', $baby->id)->latest('occurred_at')->value('occurred_at')?->toIso8601String(),
            'lastDiaperAt' => DiaperLog::where('baby_id', $baby->id)->latest('occurred_at')->value('occurred_at')?->toIso8601String(),
            'sleepingId' => $sleep?->id,
            'breastActive' => ActiveBreastFeed::where('baby_id', $baby->id)->exists(),
        ]);
    }
}
