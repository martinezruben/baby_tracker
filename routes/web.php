<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentFileController;
use App\Http\Controllers\BabyAdminController;
use App\Http\Controllers\BabySelectionController;
use App\Http\Controllers\BreastFeedController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CaretakerAdminController;
use App\Http\Controllers\CaretakerPinController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LifeEventController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MoreController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PushTestController;
use App\Http\Controllers\RegistrarController;
use App\Http\Controllers\ReminderActionController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SleepWakeController;
use App\Http\Controllers\VaccineController;
use App\Http\Controllers\VaccineScheduleController;
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
        Route::get('/registros/{type}/{id}/editar', [RegistrarController::class, 'edit'])->whereNumber('id')->name('records.edit');
        Route::put('/registros/{type}/{id}', [ActivityController::class, 'update'])->whereNumber('id')->name('records.update');
        Route::delete('/registros/{type}/{id}', [ActivityController::class, 'destroy'])->whereNumber('id')->name('records.destroy');
        Route::get('/bitacora', LogController::class)->name('log');
        Route::get('/calendario', CalendarController::class)->name('calendar');

        Route::prefix('citas')->name('appointments.')->group(function () {
            Route::get('/', [AppointmentController::class, 'index'])->name('index');
            Route::get('/nueva', [AppointmentController::class, 'create'])->name('create');
            Route::post('/', [AppointmentController::class, 'store'])->name('store');
            Route::get('/{appointment}', [AppointmentController::class, 'show'])->name('show');
            Route::get('/{appointment}/editar', [AppointmentController::class, 'edit'])->name('edit');
            Route::put('/{appointment}', [AppointmentController::class, 'update'])->name('update');
            Route::delete('/{appointment}', [AppointmentController::class, 'destroy'])->name('destroy');
            Route::post('/{appointment}/archivos', [AppointmentFileController::class, 'store'])->name('files.store');
            Route::get('/{appointment}/archivos/{attachment}', [AppointmentFileController::class, 'show'])->name('files.show');
            Route::delete('/{appointment}/archivos/{attachment}', [AppointmentFileController::class, 'destroy'])->name('files.destroy');
            Route::post('/{appointment}/medicinas', [PrescriptionController::class, 'store'])->name('prescriptions.store');
        });
        Route::prefix('fotos')->name('photos.')->group(function () {
            Route::get('/', [PhotoController::class, 'index'])->name('index');
            Route::post('/', [PhotoController::class, 'store'])->name('store');
            Route::get('/{photo}', [PhotoController::class, 'show'])->name('show');
            Route::patch('/{photo}', [PhotoController::class, 'update'])->name('update');
            Route::delete('/{photo}', [PhotoController::class, 'destroy'])->name('destroy');
            Route::post('/{photo}/favorita', [PhotoController::class, 'favorite'])->name('favorite');
            Route::delete('/{photo}/ubicacion', [PhotoController::class, 'hideLocation'])->name('location.hide');
            Route::get('/{photo}/archivo', [PhotoController::class, 'file'])->name('file');
            Route::get('/{photo}/miniatura', [PhotoController::class, 'thumb'])->name('thumb');
        });
        Route::prefix('eventos')->name('events.')->group(function () {
            Route::get('/', [LifeEventController::class, 'index'])->name('index');
            Route::get('/nuevo', [LifeEventController::class, 'create'])->name('create');
            Route::post('/', [LifeEventController::class, 'store'])->name('store');
            Route::get('/{event}', [LifeEventController::class, 'show'])->name('show');
            Route::get('/{event}/editar', [LifeEventController::class, 'edit'])->name('edit');
            Route::put('/{event}', [LifeEventController::class, 'update'])->name('update');
            Route::delete('/{event}', [LifeEventController::class, 'destroy'])->name('destroy');
            Route::post('/{event}/fotos', [LifeEventController::class, 'addPhotos'])->name('photos.store');
        });
        Route::prefix('vacunas')->name('vaccines.')->group(function () {
            Route::get('/', [VaccineController::class, 'index'])->name('index');
            Route::get('/aplicar', [VaccineController::class, 'create'])->name('create');
            Route::post('/', [VaccineController::class, 'store'])->name('store');
            Route::get('/esquema', [VaccineScheduleController::class, 'show'])->name('schedule');
            Route::patch('/esquema/nombre', [VaccineScheduleController::class, 'name'])->name('schedule.name');
            Route::post('/esquema/dosis', [VaccineScheduleController::class, 'store'])->name('schedule.store');
            Route::delete('/esquema/dosis/{dose}', [VaccineScheduleController::class, 'destroy'])->name('schedule.destroy');
            Route::post('/esquema/importar', [VaccineScheduleController::class, 'import'])->name('schedule.import');
            Route::post('/{id}/restaurar', [VaccineController::class, 'restore'])->whereNumber('id')->name('restore');
            Route::delete('/{vaccination}', [VaccineController::class, 'destroy'])->name('destroy');
        });
        Route::delete('/recetas/{prescription}', [PrescriptionController::class, 'destroy'])->name('prescriptions.destroy');
        Route::post('/dosis/{dose}/dada', [PrescriptionController::class, 'give'])->name('doses.give');
        Route::post('/dosis/{dose}/omitir', [PrescriptionController::class, 'skip'])->name('doses.skip');
        Route::post('/dosis/{dose}/deshacer', [PrescriptionController::class, 'revert'])->name('doses.revert');
        Route::post('/registros/{type}/{id}/restaurar', [ActivityController::class, 'restore'])->whereNumber('id')->name('records.restore');
        Route::patch('/sueno/{log}/despertar', SleepWakeController::class)->name('sleep.wake');
        Route::patch('/sueno/{log}/reabrir', [SleepWakeController::class, 'reopen'])->name('sleep.reopen');

        Route::post('/salir', [SessionController::class, 'destroy'])->name('logout');
        Route::patch('/cuidador/idioma', [SettingsController::class, 'language'])->name('caretaker.language');

        Route::middleware('admin')->prefix('ajustes')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::get('/preferencias', [SettingsController::class, 'preferences'])->name('preferences');
            Route::put('/preferencias', [SettingsController::class, 'updatePreferences'])->name('preferences.update');

            Route::get('/cuidadores', [CaretakerAdminController::class, 'index'])->name('caretakers');
            Route::post('/cuidadores', [CaretakerAdminController::class, 'store'])->name('caretakers.store');
            Route::put('/cuidadores/{caretaker}', [CaretakerAdminController::class, 'update'])->name('caretakers.update');
            Route::post('/cuidadores/{caretaker}/baja', [CaretakerAdminController::class, 'deactivate'])->name('caretakers.deactivate');
            Route::post('/cuidadores/{caretaker}/alta', [CaretakerAdminController::class, 'reactivate'])->name('caretakers.reactivate');

            Route::get('/bebes', [BabyAdminController::class, 'index'])->name('babies');
            Route::post('/bebes', [BabyAdminController::class, 'store'])->name('babies.store');
            Route::put('/bebes/{baby}', [BabyAdminController::class, 'update'])->name('babies.update');
            Route::delete('/bebes/{baby}', [BabyAdminController::class, 'destroy'])->name('babies.destroy');
        });

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
