<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\DiaperType;
use App\Enums\FeedType;
use App\Models\Caretaker;
use App\Models\DiaperLog;
use App\Models\FeedLog;
use App\Models\SleepLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00'); // 08:00 en Santo Domingo
        $this->mama = app(SetupFamily::class)->handle([
            'family_name' => 'Ruiz', 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => 'Sofía', 'baby_birth_date' => '2026-06-01',
        ]);
        $this->mama->family->settings->update(['timezone' => 'America/Santo_Domingo']);
        $this->actingAs($this->mama);
    }

    private function owner(): array
    {
        $baby = $this->mama->family->babies()->first();

        return ['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'caretaker_id' => $this->mama->id];
    }

    public function test_report_groups_by_family_day_and_averages_active_days(): void
    {
        // 02:00 UTC del 3 = 22:00 del día 2 en Santo Domingo.
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 02:00:00', 'started_at' => '2026-11-03 02:00:00', 'type' => FeedType::Bottle, 'amount' => 100, 'unit' => 'ml']);
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:00:00', 'started_at' => '2026-11-03 11:00:00', 'type' => FeedType::Bottle, 'amount' => 80, 'unit' => 'ml']);
        DiaperLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:30:00', 'type' => DiaperType::Both]);
        SleepLog::create($this->owner() + ['started_at' => '2026-11-03 05:00:00', 'ended_at' => '2026-11-03 07:00:00', 'duration_minutes' => 120, 'type' => 'nap']);

        $this->get('/reportes?dias=7')->assertInertia(fn (Assert $page) => $page
            ->component('Reportes')
            ->where('report.days', 7)
            ->has('report.series', 7)
            ->where('report.series.6.date', '2026-11-03')
            ->where('report.series.6.feeds', 1)
            ->where('report.series.6.volume', 80)
            ->where('report.series.6.wet', 1)
            ->where('report.series.6.dirty', 1)
            ->where('report.series.5.date', '2026-11-02')
            ->where('report.series.5.feeds', 1)
            ->where('report.series.6.sleep_minutes', 120)
            ->where('report.active_days', 2)
            ->where('report.averages.feeds', 1));
    }

    public function test_range_is_limited_to_known_values(): void
    {
        $this->get('/reportes?dias=999')->assertInertia(fn (Assert $page) => $page->where('report.days', 7));
        $this->get('/reportes?dias=30')->assertInertia(fn (Assert $page) => $page->where('report.days', 30)->has('report.series', 30));
    }

    public function test_csv_export_lists_all_entries_with_bom(): void
    {
        DiaperLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:30:00', 'type' => DiaperType::Wet, 'notes' => 'Rojez']);
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:00:00', 'started_at' => '2026-11-03 11:00:00', 'type' => FeedType::Bottle, 'amount' => 90, 'unit' => 'ml']);

        DiaperLog::create($this->owner() + ['occurred_at' => '2026-11-03 10:30:00', 'type' => DiaperType::Dirty, 'notes' => '=HYPERLINK("http://x")']);

        $response = $this->get('/reportes/exportar')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Fecha y hora",Tipo,"Registrado por",Detalles', $csv);
        $this->assertStringContainsString('2026-11-03T07:30,Pañal,Mamá', $csv);
        $this->assertStringContainsString('Rojez', $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString('notes: =HYPERLINK', $csv); // dentro de «clave: valor» ya no empieza por =
        $this->assertStringContainsString('Biberón', $csv);
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_requires_login_and_stays_inside_the_family(): void
    {
        DiaperLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:30:00', 'type' => DiaperType::Wet, 'notes' => 'Secreto']);

        $other = app(SetupFamily::class)->handle([
            'family_name' => 'Pérez', 'language' => 'es', 'admin_name' => 'Ana', 'pin' => '482915',
            'baby_first_name' => 'Luis', 'baby_birth_date' => '2026-06-01',
        ]);
        $this->actingAs($other);
        $this->assertStringNotContainsString('Secreto', $this->get('/reportes/exportar')->streamedContent());

        auth()->logout();
        $this->get('/reportes')->assertRedirect();
        $this->get('/reportes/exportar')->assertRedirect();
    }
}
