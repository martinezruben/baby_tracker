<?php

namespace App\Console\Commands;

use App\Actions\SyncVaccineReminders;
use App\Models\Baby;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vaccines:sync')]
#[Description('Reprograma los avisos de vacunas pendientes de todos los bebés (mueve la ventana de repeticiones)')]
class SyncVaccineRemindersCommand extends Command
{
    public function handle(SyncVaccineReminders $sync): int
    {
        $count = 0;

        Baby::query()->with('family.settings')->chunkById(50, function ($babies) use ($sync, &$count) {
            foreach ($babies as $baby) {
                $sync->handle($baby);
                $count++;
            }
        });

        $this->info("Bebés sincronizados: {$count}");

        return self::SUCCESS;
    }
}
