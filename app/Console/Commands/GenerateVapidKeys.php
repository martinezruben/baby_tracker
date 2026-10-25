<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('webpush:vapid {--write : Escribe las claves en el .env si aún no existen}')]
#[Description('Genera un par de claves VAPID para Web Push')]
class GenerateVapidKeys extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        if ($this->option('write')) {
            return $this->writeToEnv($keys);
        }

        $this->line("VAPID_PUBLIC_KEY={$keys['publicKey']}");
        $this->line("VAPID_PRIVATE_KEY={$keys['privateKey']}");
        $this->newLine();
        $this->warn('Cópialas a tu .env. La clave privada nunca se sube al repositorio.');

        return self::SUCCESS;
    }

    private function writeToEnv(array $keys): int
    {
        $path = base_path('.env');
        $env = is_file($path) ? file_get_contents($path) : '';

        if (preg_match('/^VAPID_PRIVATE_KEY=.+$/m', $env)) {
            $this->error('Ya hay claves VAPID en el .env. Cambiarlas invalida todas las suscripciones existentes; bórralas a mano si de verdad quieres hacerlo.');

            return self::FAILURE;
        }

        $env = preg_replace('/^VAPID_(PUBLIC|PRIVATE)_KEY=.*\R?/m', '', $env);
        file_put_contents($path, rtrim($env)."\n\nVAPID_PUBLIC_KEY={$keys['publicKey']}\nVAPID_PRIVATE_KEY={$keys['privateKey']}\n");

        $this->info('Claves VAPID guardadas en el .env.');

        return self::SUCCESS;
    }
}
