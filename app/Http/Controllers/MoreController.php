<?php

namespace App\Http\Controllers;

use App\Support\ActiveBaby;
use App\Support\BreastFeedSession;
use App\Support\DashboardData;
use App\Support\FamilyClock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoreController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Mas', [
            'caretakers' => $user->family->caretakers()->where('is_active', true)->count(),
            'slug' => $user->family->slug,
            'badges' => ['vaccines' => (new DashboardData(FamilyClock::for($user->family), app(BreastFeedSession::class)))->vaccineSummary(ActiveBaby::for($request))['overdue']],
        ]);
    }

    /** Avisos, instalación, apariencia, idioma y PIN: lo personal de cada cuidador en este dispositivo. */
    public function device(Request $request): Response
    {
        return Inertia::render('Ajustes/Dispositivo', [
            'language' => $request->user()->language,
            'languages' => SettingsController::LANGUAGES,
        ]);
    }
}
