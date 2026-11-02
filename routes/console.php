<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:dispatch')->everyMinute()->withoutOverlapping();

Schedule::command('backup:run')->dailyAt(config('backup.time'))->withoutOverlapping();

// Corre la ventana de repeticiones de los avisos de vacunas pendientes (idempotente).
Schedule::command('vaccines:sync')->dailyAt('01:00')->withoutOverlapping();
