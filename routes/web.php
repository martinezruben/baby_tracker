<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BabySelectionController;
use App\Http\Controllers\BreastFeedController;
use App\Http\Controllers\CaretakerPinController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MoreController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PushTestController;
use App\Http\Controllers\RegistrarController;
use App\Http\Controllers\ReminderActionController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SleepWakeController;
use Illuminate\Support\Facades\Route;

// Asistente inicial: solo mientras no exista ninguna familia.
Route::middleware('setup:pending')->group(function () {
    Route::get('/configuracion', [SetupController::class, 'show'])->name('setup.show');
    Route::post('/configuracion', [SetupController::class, 'store'])->name('setup.store');
});

Route::middleware('setup:required')->group(function () {
    Route::get('/entrar/{slug?}', [SessionController::class, 'create'])->name('login');
    Route::post('/entrar', [SessionController::class, 'store'])->name('login.store');

    Route::middleware('auth')->group(function () {
        Route::get('/', DashboardController::class)->name('inicio');

        Route::get('/mas', MoreController::class)->name('more');
        Route::get('/registrar', [RegistrarController::class, 'hub'])->name('registrar');
        Route::get('/registrar/pecho', [BreastFeedController::class, 'create'])->name('breast.create');
        Route::post('/registrar/pecho', [BreastFeedController::class, 'start'])->name('breast.start');
        Route::patch('/pecho/{op}', [BreastFeedController::class, 'update'])->name('breast.update');
        Route::delete('/pecho', [BreastFeedController::class, 'destroy'])->name('breast.discard');
        Route::get('/registrar/{type}', [RegistrarController::class, 'create'])->name('registrar.create');
        Route::post('/registrar/{type}', [ActivityController::class, 'store'])->name('records.store');
        Route::post('/registrar/{type}/repetir', [ActivityController::class, 'repeat'])->name('records.repeat');
        Route::delete('/registros/{type}/{id}', [ActivityController::class, 'destroy'])->whereNumber('id')->name('records.destroy');
        Route::patch('/sueno/{log}/despertar', SleepWakeController::class)->name('sleep.wake');
        Route::patch('/sueno/{log}/reabrir', [SleepWakeController::class, 'reopen'])->name('sleep.reopen');

        Route::post('/salir', [SessionController::class, 'destroy'])->name('logout');
        Route::put('/cuidador/pin', [CaretakerPinController::class, 'update'])->name('caretaker.pin');
        Route::post('/push/suscripciones', [PushSubscriptionController::class, 'store'])->name('push.store');
        Route::delete('/push/suscripciones', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
        Route::post('/push/prueba', PushTestController::class)->name('push.test');

        Route::prefix('recordatorios')->name('reminders.')->group(function () {
            Route::get('/', [ReminderController::class, 'index'])->name('index');
            Route::get('/nuevo', [ReminderController::class, 'create'])->name('create');
            Route::post('/', [ReminderController::class, 'store'])->name('store');
            Route::get('/{rule}/editar', [ReminderController::class, 'edit'])->name('edit');
            Route::put('/{rule}', [ReminderController::class, 'update'])->name('update');
            Route::patch('/{rule}/activar', [ReminderController::class, 'toggle'])->name('toggle');
            Route::delete('/{rule}', [ReminderController::class, 'destroy'])->name('destroy');
        });

        Route::post('/bebes/{baby}/seleccionar', [BabySelectionController::class, 'store'])->name('babies.select');
    });
});

// Botones Confirmar/Posponer de los avisos: enlace firmado y con caducidad, sin sesión.
Route::post('/avisos/{target}/accion', ReminderActionController::class)
    ->middleware('signed:relative')
    ->name('reminders.act');
