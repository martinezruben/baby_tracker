<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Contraste WCAG AA (4.5:1) de los pares de texto de cada tema, leídos de resources/css/app.css. */
class TemaContrasteTest extends TestCase
{
    private const KINDS = ['food', 'sleep', 'diaper', 'medicine', 'vaccine', 'appointment', 'growth', 'moment'];

    private string $css;

    protected function setUp(): void
    {
        $this->css = file_get_contents(__DIR__.'/../../resources/css/app.css');
    }

    /** @return array<string, string> */
    private function tokens(string $from, string $to): array
    {
        $start = strpos($this->css, $from);
        $block = substr($this->css, $start, strpos($this->css, $to, $start) - $start);
        preg_match_all('/--color-([a-z0-9-]+):\s*(#[0-9a-fA-F]{6})/', $block, $m, PREG_SET_ORDER);

        return array_column($m, 2, 1);
    }

    /** @return array<string, array<string, string>> */
    private function themes(): array
    {
        $light = $this->tokens('@theme', 'body {');

        return [
            'light' => $light,
            'dark' => $this->tokens("[data-theme='dark']", "[data-theme='nursery']") + $light,
            'nursery' => $this->tokens("[data-theme='nursery']", '.nursery-dim') + $light,
        ];
    }

    private function luminance(string $hex): float
    {
        $c = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
        $c = array_map(fn ($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private function ratio(string $a, string $b): float
    {
        [$hi, $lo] = [max($this->luminance($a), $this->luminance($b)), min($this->luminance($a), $this->luminance($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    public function test_text_pairs_meet_aa_in_every_theme(): void
    {
        $pairs = [
            ['ink', 'canvas'], ['ink', 'surface'], ['ink-mid', 'surface'], ['ink-soft', 'canvas'], ['ink-soft', 'surface'],
            ['ink-soft', 'track'], ['ink-soft', 'neutral-tile'], ['on-primary', 'primary'], ['on-ink', 'ink'],
            ['primary', 'surface'], ['primary', 'canvas'], ['accent-ink', 'accent'], ['danger-ink', 'surface'], ['danger-ink', 'danger-soft'],
        ];
        foreach (self::KINDS as $kind) {
            $pairs[] = ["{$kind}-ink", $kind];
            $pairs[] = ["{$kind}-ink", 'surface'];
        }

        foreach ($this->themes() as $theme => $tokens) {
            foreach ($pairs as [$fg, $bg]) {
                $this->assertArrayHasKey($fg, $tokens, "{$theme}: falta {$fg}");
                $this->assertArrayHasKey($bg, $tokens, "{$theme}: falta {$bg}");
                $this->assertGreaterThanOrEqual(4.5, $this->ratio($tokens[$fg], $tokens[$bg]), "{$theme}: {$fg} sobre {$bg}");
            }
            $this->assertGreaterThanOrEqual(4.5, $this->ratio('#ffffff', $tokens['night']), "{$theme}: texto blanco sobre la tarjeta principal");
        }
    }

    public function test_dark_and_nursery_are_not_pure_black_except_nursery_background(): void
    {
        $themes = $this->themes();

        $this->assertNotSame('#000000', $themes['dark']['canvas']);
        $this->assertNotSame('#000000', $themes['nursery']['canvas']); // casi negro, sin halo
        $this->assertLessThan(0.01, $this->luminance($themes['nursery']['canvas']));
    }

    public function test_nursery_text_is_amber_not_white(): void
    {
        $ink = $this->themes()['nursery']['ink'];
        [$r, $g, $b] = [hexdec(substr($ink, 1, 2)), hexdec(substr($ink, 3, 2)), hexdec(substr($ink, 5, 2))];

        $this->assertGreaterThan($b + 60, $r); // sin azul: no altera el sueño
    }
}
