<?php

namespace App\Http\Controllers;

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
            'language' => $user->language,
            'languages' => SettingsController::LANGUAGES,
        ]);
    }
}
