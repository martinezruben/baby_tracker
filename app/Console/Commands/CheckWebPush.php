<?php

namespace App\Console\Commands;

use App\Models\Caretaker;
use App\Services\Notifications\Channels\WebPushChannel;
use App\Support\PushDiagnostics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('webpush:check {--send= : Nombre del cuidador al que enviar un aviso de prueba}')]
#[Description('Revisa lo necesario para los avisos push y, opcionalmente, envía uno de prueba')]
class CheckWebPush extends Command
{
    public function handle(PushDiagnostics $diagnostics, WebPushChannel $channel): int
    {
        $r = $diagnostics->report();
        $failed = false;

        $row = function (string $label, bool $ok, string $fix = '') use (&$failed) {
            $this->line(($ok ? '  <info>OK</info>   ' : '  <error>FALLA</error> ').$label.(! $ok && $fix ? "\n         → {$fix}" : ''));
            $failed = $failed || ! $ok;
        };

        $this->info('Avisos push');
        $row('Claves VAPID en el .env', $r['vapid_configured'], 'php artisan webpush:vapid --write (y reinicia/limpia la caché de configuración)');
        $row('Clave pública VAPID con formato válido', $r['vapid_valid'], 'Vuelve a generarla; debe ser la base64url de 65 bytes que imprime webpush:vapid');
        $row('APP_URL usa https (obligatorio salvo en localhost)', $r['secure'], 'Pon APP_URL=https://tu-dominio y sirve el sitio con HTTPS');
        foreach ($r['extensions'] as $name => $loaded) {
            $row("Extensión PHP {$name}", $loaded, "Instala/activa php-{$name}");
        }
        $row(
            'Cron: reminders:dispatch corrió en los últimos '.PushDiagnostics::STALE_MINUTES.' min'.($r['dispatcher_last_run'] ? " (última: {$r['dispatcher_last_run']})" : ' (nunca)'),
            (bool) $r['dispatcher_ok'],
            'Programa cada minuto: * * * * * php '.base_path('artisan').' schedule:run >> /dev/null 2>&1',
        );
        $row("Avisos vencidos sin enviar: {$r['overdue']}", $r['overdue'] === 0, 'Suele ser el cron parado o un fallo de entrega; revisa storage/logs/laravel.log');
        $this->line("  Dispositivos con avisos activados: {$r['devices_total']}");

        if ($name = $this->option('send')) {
            $caretaker = Caretaker::where('name', $name)->first();
            if (! $caretaker) {
                $this->error("No existe un cuidador llamado «{$name}».");

                return self::FAILURE;
            }

            try {
                $channel->sendTest($caretaker);
                $this->info("Aviso de prueba enviado a {$caretaker->name}. Debería sonar en sus dispositivos.");
            } catch (Throwable $e) {
                $this->error('No se pudo enviar: '.$e->getMessage());
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
