<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\FeedType;
use App\Models\Caretaker;
use App\Models\FeedLog;
use App\Support\Units;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UnidadLecheTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00');
        $this->mama = app(SetupFamily::class)->handle([
            'family_name' => 'Ruiz', 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => 'Sofía', 'baby_birth_date' => '2026-06-01',
        ]);
        $this->mama->family->settings->update(['timezone' => 'America/Santo_Domingo']);
        $this->actingAs($this->mama);
    }

    private function bottle(float $amount, string $unit, string $at = '2026-11-03 09:00:00'): FeedLog
    {
        $baby = $this->mama->family->babies()->first();

        return FeedLog::create(['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'caretaker_id' => $this->mama->id, 'occurred_at' => $at, 'started_at' => $at, 'type' => FeedType::Bottle, 'amount' => $amount, 'unit' => $unit]);
    }

    public function test_conversion_and_rounding(): void
    {
        $this->assertEqualsWithDelta(29.5735, Units::volume(1, 'oz', 'ml'), 0.0001);
        $this->assertEqualsWithDelta(4.0, Units::volume(118.294, 'ml', 'oz'), 0.001);
        $this->assertSame(120.0, Units::display(4.06, 'oz', 'ml'));
        $this->assertSame(4.1, Units::display(120, 'ml', 'oz'));
        $this->assertSame(90.0, Units::display(90, 'ml', 'ml'));
        $this->assertSame(5.0, Units::volume(5, 'otra', 'ml')); // unidad desconocida: no se toca
    }

    public function test_the_unit_can_be_changed_in_family_settings(): void
    {
        $this->get('/ajustes/preferencias')->assertInertia(fn (Assert $page) => $page->where('prefs.volume_unit', 'ml'));

        $this->put('/ajustes/preferencias', $this->prefs(['volume_unit' => 'oz']))->assertRedirect();

        $this->assertSame('oz', $this->mama->family->settings->fresh()->volume_unit);
        $this->get('/registrar/biberon')->assertInertia(fn (Assert $page) => $page->where('volumeUnit', 'oz'));
    }

    public function test_home_summary_adds_ml_and_oz_in_the_family_unit(): void
    {
        $this->bottle(120, 'ml');
        $this->bottle(4, 'oz', '2026-11-03 10:00:00'); // 118,3 ml

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('summary.feed.unit', 'ml')->where('summary.feed.amount', 238));

        $this->mama->family->settings->update(['volume_unit' => 'oz']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('summary.feed.unit', 'oz')->where('summary.feed.amount', 8.1));
    }

    public function test_log_shows_each_bottle_in_the_family_unit(): void
    {
        $this->bottle(120, 'ml');
        $this->mama->family->settings->update(['volume_unit' => 'oz']);

        $this->get('/bitacora')->assertInertia(fn (Assert $page) => $page->where('entries.0.data.unit', 'oz')->where('entries.0.data.amount', 4.1));
    }

    public function test_reports_convert_and_expose_the_unit(): void
    {
        $this->bottle(120, 'ml');
        $this->bottle(4, 'oz', '2026-11-03 10:00:00');
        $this->mama->family->settings->update(['volume_unit' => 'oz']);

        $this->get('/reportes')->assertInertia(fn (Assert $page) => $page
            ->where('report.volume_unit', 'oz')->where('report.series.6.volume', 8.1)->where('report.averages.volume', 8.1));
    }

    public function test_editing_keeps_the_unit_the_entry_was_saved_in(): void
    {
        $log = $this->bottle(120, 'ml');
        $this->mama->family->settings->update(['volume_unit' => 'oz']);

        $this->get("/registros/biberon/{$log->id}/editar")->assertInertia(fn (Assert $page) => $page->where('record.unit', 'ml')->where('record.amount', 120));
    }

    /** @return array<string, mixed> */
    private function prefs(array $override): array
    {
        $s = $this->mama->family->settings->fresh();

        return $override + [
            'family_name' => $this->mama->family->name, 'language' => $s->language, 'timezone' => $s->timezone, 'time_format' => $s->time_format,
            'volume_unit' => $s->volume_unit, 'weight_unit' => $s->weight_unit, 'height_unit' => $s->height_unit,
            'temperature_unit' => $s->temperature_unit, 'growth_chart_standard' => $s->growth_chart_standard,
        ];
    }
}
