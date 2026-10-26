<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('backup:restore {file : Ruta del ZIP} {--wipe : Borra antes todo lo que haya (pide confirmación)}')]
#[Description('Restaura un respaldo. Pide el PIN nuevo del administrador, porque el respaldo no guarda PIN.')]
class RestoreBackup extends Command
{
    public function handle(BackupService $backups): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("No existe el archivo {$file}.");

            return self::FAILURE;
        }

        $wipe = (bool) $this->option('wipe');
        if ($wipe && ! $this->confirm('Esto BORRA todos los datos actuales y los reemplaza por el respaldo. ¿Continuar?')) {
            return self::FAILURE;
        }

        $pin = $this->secret('PIN nuevo del administrador (4 a 10 números)');
        if (! preg_match('/^\d{4,10}$/', (string) $pin)) {
            $this->error('El PIN debe tener de 4 a 10 números.');

            return self::FAILURE;
        }

        try {
            $result = $backups->restore($file, $pin, $wipe);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Restaurado: {$result['rows']} filas de {$result['tables']} tablas, {$result['files']} archivos.");
        if ($result['reset_pin']) {
            $this->warn('Estos cuidadores necesitan un PIN nuevo (Ajustes > Cuidadores): '.implode(', ', $result['reset_pin']));
        }

        return self::SUCCESS;
    }
}
