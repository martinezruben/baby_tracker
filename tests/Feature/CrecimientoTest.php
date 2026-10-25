<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Models\Caretaker;
use App\Models\Measurement;
use App\Support\Growth\GrowthStandards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CrecimientoTest extends TestCase
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
        $this->mama->family->babies()->first()->update(['gender' => 'female']);
        $this->actingAs($this->mama);
    }

    private function measure(string $type, string $on, float $value, string $unit): void
    {
        $baby = $this->mama->family->babies()->first();
        Measurement::create(['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'caretaker_id' => $this->mama->id, 'measured_on' => $on, 'type' => $type, 'value' => $value, 'unit' => $unit]);
    }

    public function test_the_median_has_z_zero_and_percentile_50(): void
    {
        // Mediana OMS peso al nacer de niñas (tabla oficial: M = 3,2322 kg).
        $lms = GrowthStandards::lms('who', 'weight', 'female', 0);
        $this->assertEqualsWithDelta(3.2322, $lms[1], 0.0001);
        $this->assertEqualsWithDelta(0.0, GrowthStandards::zScore('who', 'weight', 'female', 0, $lms[1]), 1e-9);
        $this->assertEqualsWithDelta(50.0, GrowthStandards::percentile(0), 0.001);
    }

    public function test_percentile_function_matches_the_normal_distribution(): void
    {
        $this->assertEqualsWithDelta(84.134, GrowthStandards::percentile(1.0), 0.01);
        $this->assertEqualsWithDelta(2.275, GrowthStandards::percentile(-2.0), 0.01);
        $this->assertEqualsWithDelta(97.0, GrowthStandards::percentile(1.8807936), 0.01);
    }

    public function test_value_at_a_z_round_trips(): void
    {
        $lms = GrowthStandards::lms('who', 'height', 'male', 100);
        $value = GrowthStandards::valueAt($lms, 1.5);
        $this->assertEqualsWithDelta(1.5, GrowthStandards::zScore('who', 'height', 'male', 100, $value), 1e-6);
    }

    public function test_interpolates_between_table_ages_and_rejects_out_of_range(): void
    {
        $this->assertNotNull(GrowthStandards::lms('who', 'weight', 'male', 10.5));
        $this->assertNull(GrowthStandards::lms('cdc', 'weight', 'male', 1200));
        $this->assertNull(GrowthStandards::lms('who', 'weight', 'male', -1));
    }

    public function test_page_returns_bands_and_baby_points_with_percentile(): void
    {
        $this->measure('weight', '2026-06-01', 3.2322, 'kg'); // la mediana al nacer
        $this->measure('weight', '2026-10-01', 6.5, 'kg');

        $this->get('/crecimiento')->assertInertia(fn (Assert $page) => $page
            ->component('Crecimiento')
            ->where('report.measure', 'weight')
            ->where('report.standard', 'who')
            ->where('report.sex_known', true)
            ->has('report.points', 2)
            ->where('report.points.0.percentile', fn ($v) => abs($v - 50) < 1)
            ->where('report.points.1.day', 122)
            ->has('report.bands.3')->has('report.bands.97')
            ->where('report.x_max', fn ($v) => $v >= 183));
    }

    public function test_pounds_are_converted_for_the_percentile_and_shown_in_family_units(): void
    {
        $this->mama->family->settings->update(['weight_unit' => 'lb']);
        $this->measure('weight', '2026-06-01', 3.2322 / 0.45359237, 'lb');

        $this->get('/crecimiento')->assertInertia(fn (Assert $page) => $page
            ->where('report.unit', 'lb')->where('report.points.0.value', 7.13)->where('report.points.0.percentile', fn ($v) => abs($v - 50) < 1));
    }

    public function test_without_sex_there_are_no_curves(): void
    {
        $this->mama->family->babies()->first()->update(['gender' => null]);
        $this->measure('weight', '2026-10-01', 6.5, 'kg');

        $this->get('/crecimiento')->assertInertia(fn (Assert $page) => $page
            ->where('report.sex_known', false)->where('report.bands', null)->where('report.points.0.percentile', null));
    }

    public function test_cdc_is_used_when_chosen_and_who_after_36_months(): void
    {
        $this->mama->family->settings->update(['growth_chart_standard' => 'cdc']);
        $this->get('/crecimiento?medida=head')->assertInertia(fn (Assert $page) => $page->where('report.standard', 'cdc')->where('report.measure', 'head'));

        $this->mama->family->babies()->first()->update(['birth_date' => '2022-01-01']);
        $this->get('/crecimiento')->assertInertia(fn (Assert $page) => $page->where('report.standard', 'who'));
    }

    public function test_requires_login(): void
    {
        auth()->logout();
        $this->get('/crecimiento')->assertRedirect();
    }
}
