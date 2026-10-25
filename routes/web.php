<?php

use App\Http\Controllers\BabySelectionController;
use App\Http\Controllers\CaretakerPinController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReminderActionController;
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
        Route::post('/push/suscripciones', [PushSubscriptionController::class, 'store'])->name('push.store');
        Route::delete('/push/suscripciones', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
        Route::post('/bebes/{baby}/seleccionar', [BabySelectionController::class, 'store'])->name('babies.select');
    });
});

// Botones Confirmar/Posponer de los avisos: enlace firmado y con caducidad, sin sesión.
Route::post('/avisos/{target}/accion', ReminderActionController::class)
    ->middleware('signed:relative')
    ->name('reminders.act');
