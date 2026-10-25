<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:dispatch')]
#[Description('Envía los avisos programados que ya vencieron')]
class DispatchReminders extends Command
{
    public function handle(NotificationDispatcher $dispatcher): int
    {
        $count = $dispatcher->dispatchDue();
        $this->info("Avisos procesados: {$count}");

        return self::SUCCESS;
    }
}
