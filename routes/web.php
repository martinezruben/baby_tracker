<?php

use App\Http\Controllers\BabySelectionController;
use App\Http\Controllers\CaretakerPinController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Asistente inicial: solo mientras no exista ninguna familia.
Route::middleware('setup:pending')->group(function () {
    Route::get('/configuracion', [SetupController::class, 'show'])->name('setup.show');
    Route::post('/configuracion', [SetupController::class, 'store'])->name('setup.store');
});

Route::middleware('setup:required')->group(function () {
    Route::get('/entrar/{slug?}', [SessionController::class, 'create'])->name('login');
    Route::post('/entrar', [SessionController::class, 'store'])->name('login.store');

    Route::middleware('auth')->group(function () {
        Route::get('/', fn () => Inertia::render('Inicio'))->name('inicio');
        Route::post('/salir', [SessionController::class, 'destroy'])->name('logout');
        Route::put('/cuidador/pin', [CaretakerPinController::class, 'update'])->name('caretaker.pin');
        Route::post('/bebes/{baby}/seleccionar', [BabySelectionController::class, 'store'])->name('babies.select');
    });
});
