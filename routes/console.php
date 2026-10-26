<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:dispatch')->everyMinute()->withoutOverlapping();

Schedule::command('backup:run')->dailyAt(config('backup.time'))->withoutOverlapping();
