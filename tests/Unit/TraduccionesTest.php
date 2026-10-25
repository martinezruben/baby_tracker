<?php

namespace Tests\Unit;

use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Red de seguridad para "ningún texto fijo en los componentes": todo t('clave') del frontend
 * existe en español, y cada idioma tiene exactamente las mismas claves que el español.
 */
class TraduccionesTest extends TestCase
{
    private function flat(string $locale): array
    {
        return Arr::dot(require dirname(__DIR__, 2)."/lang/{$locale}/ui.php");
    }

    /** @return array<string, string> clave => archivo donde se usa */
    private function usedKeys(): array
    {
        $used = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/js'));

        foreach ($files as $file) {
            if (! preg_match('/\.jsx?$/', (string) $file) || str_ends_with((string) $file, 'i18n.js')) {
                continue;
            }

            preg_match_all("/\bt(?:\.choice)?\(\s*'([a-z_]+(?:\.[a-z_0-9]+)+)'/", file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                $used[$key] = basename((string) $file);
            }
        }

        return $used;
    }

    public function test_every_key_used_in_the_frontend_exists_in_spanish(): void
    {
        $es = $this->flat('es');
        $missing = array_filter($this->usedKeys(), fn ($file, $key) => ! array_key_exists($key, $es), ARRAY_FILTER_USE_BOTH);

        $this->assertSame([], $missing, 'Claves usadas en el frontend que faltan en lang/es/ui.php');
    }

    public function test_dynamic_keys_used_by_the_screens_exist(): void
    {
        $es = $this->flat('es');
        $dynamic = [];

        foreach (['biberon', 'sueno', 'panal', 'medida'] as $k) {
            $dynamic[] = "home.quick_{$k}";
        }
        foreach (['hoy', 'semana', 'mes'] as $k) {
            $dynamic[] = "home.summary.range_{$k}";
        }
        foreach (['calendar', 'reminders', 'appointments', 'vaccines', 'medicines', 'growth', 'photos', 'moments', 'reports', 'log', 'allergies', 'contacts', 'settings'] as $k) {
            $dynamic[] = "home.modules.{$k}";
        }
        foreach (['biberon', 'pecho', 'panal', 'sueno', 'solidos', 'extraccion', 'bano', 'juego', 'medicina', 'medida', 'vacuna', 'cita', 'hito', 'foto', 'nota', 'animo'] as $k) {
            $dynamic[] = "registrar.tiles.{$k}";
        }
        foreach (['hated', 'disliked', 'neutral', 'liked', 'loved'] as $k) {
            $dynamic[] = "registrar.solids.enjoy_{$k}";
        }
        foreach (['tummy_time', 'indoor_play', 'outdoor_play', 'walk', 'custom'] as $k) {
            $dynamic[] = "registrar.play.{$k}";
        }
        foreach (['happy', 'calm', 'fussy', 'crying'] as $k) {
            $dynamic[] = "registrar.mood.{$k}";
        }
        foreach (['full', 'sponge'] as $k) {
            $dynamic[] = "registrar.bath.{$k}";
        }
        foreach (['wet', 'dirty', 'both', 'dry'] as $k) {
            $dynamic[] = "registrar.diaper.{$k}";
        }
        foreach (['poor', 'fair', 'good', 'excellent'] as $k) {
            $dynamic[] = "registrar.sleep.q_{$k}";
        }
        foreach (['weight', 'height', 'head_circumference', 'temperature'] as $k) {
            $dynamic[] = "registrar.measure.{$k}";
        }
        foreach (['left', 'right'] as $k) {
            array_push($dynamic, "session.side_{$k}", "session.side_{$k}_short");
        }
        foreach (['admin', 'user'] as $k) {
            $dynamic[] = "more.role_{$k}";
        }
        foreach ([0, 5, 10, 15, 30, 60, 120, 1440] as $k) {
            $dynamic[] = "reminders.offsets.{$k}";
        }
        foreach (['tooth', 'food', 'first_steps', 'crawling', 'word', 'trip', 'illness', 'other'] as $k) {
            $dynamic[] = "eventos.types.{$k}";
        }
        foreach (['tooth', 'food', 'word', 'trip'] as $k) {
            array_push($dynamic, "eventos.form.detail_{$k}", "eventos.form.detail_{$k}_ph");
        }
        foreach (['applied', 'overdue', 'soon', 'later'] as $k) {
            array_push($dynamic, "vacunas.status.{$k}", "vacunas.sections.{$k}");
        }
        foreach (['appointment', 'reminder', 'vaccine', 'dose', 'life_event'] as $k) {
            $dynamic[] = "calendario.kinds.{$k}";
        }
        foreach (['done', 'pending', 'overdue', 'skipped'] as $k) {
            $dynamic[] = "calendario.states.{$k}";
        }
        foreach (['feed', 'sleep', 'diaper', 'medicine'] as $k) {
            $dynamic[] = "calendario.counts.{$k}";
        }
        foreach (['appointment', 'vaccine', 'medicine', 'life_event', 'feed', 'sleep', 'diaper'] as $k) {
            $dynamic[] = "calendario.legend.{$k}";
        }
        foreach (['active', 'count_babies'] as $k) {
            $dynamic[] = "ajustes.hub.{$k}";
        }
        foreach (['scheduled', 'completed', 'cancelled'] as $k) {
            $dynamic[] = "citas.states.{$k}";
        }
        foreach (['pending', 'given', 'skipped'] as $k) {
            $dynamic[] = "citas.rx.{$k}";
        }
        foreach (['upcoming', 'acknowledged', 'done', 'paused'] as $k) {
            $dynamic[] = "reminders.states.{$k}";
        }

        $this->assertSame([], array_values(array_filter($dynamic, fn ($key) => ! array_key_exists($key, $es))));
    }

    public function test_english_has_exactly_the_same_keys_as_spanish(): void
    {
        $es = array_keys($this->flat('es'));
        $en = array_keys($this->flat('en'));

        $this->assertSame([], array_values(array_diff($es, $en)), 'Faltan en inglés');
        $this->assertSame([], array_values(array_diff($en, $es)), 'Sobran en inglés');
    }
}
