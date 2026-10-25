<?php

namespace App\Http\Controllers;

use App\Actions\SetupFamily;
use App\Http\Requests\SetupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Configuracion');
    }

    public function store(SetupRequest $request, SetupFamily $setup): RedirectResponse
    {
        $admin = $setup->handle($request->validated());

        $request->session()->regenerate();
        Auth::login($admin);

        return redirect()->route('inicio');
    }
}
