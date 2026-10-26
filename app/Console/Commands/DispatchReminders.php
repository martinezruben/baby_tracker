<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationDispatcher;
use App\Support\PushDiagnostics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:dispatch')]
#[Description('Envía los avisos programados que ya vencieron')]
class DispatchReminders extends Command
{
    public function handle(NotificationDispatcher $dispatcher, PushDiagnostics $diagnostics): int
    {
        // Señal de vida: así la pantalla de diagnóstico sabe si el cron (schedule:run) está corriendo.
        $diagnostics->beat();
        $count = $dispatcher->dispatchDue();
        $this->info("Avisos procesados: {$count}");

        return self::SUCCESS;
    }
}
