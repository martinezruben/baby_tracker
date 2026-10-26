<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('backup:run {--files : Incluye fotos y archivos adjuntos} {--keep= : Cuántos respaldos conservar}')]
#[Description('Crea un respaldo (ZIP) en storage/app/private/backups y borra los más antiguos')]
class RunBackup extends Command
{
    public function handle(BackupService $backups): int
    {
        $withFiles = $this->option('files') || (bool) config('backup.auto_include_files');
        $name = $backups->newName($withFiles);

        try {
            $manifest = $backups->create($backups->path($name), $withFiles);
        } catch (Throwable $e) {
            report($e);
            $this->error('No se pudo crear el respaldo: '.$e->getMessage());

            return self::FAILURE;
        }

        $rows = array_sum(array_column($manifest['tables'], 'rows'));
        $this->info("Respaldo {$name}: {$rows} filas".($withFiles ? ", {$manifest['files']} archivos" : '').'.');

        $removed = $backups->prune((int) ($this->option('keep') ?: config('backup.keep')));
        $removed && $this->line("Se borraron {$removed} respaldos antiguos.");

        return self::SUCCESS;
    }
}
