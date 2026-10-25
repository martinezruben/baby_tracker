<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Inicio', [
    'appName' => config('app.name'),
]))->name('inicio');
