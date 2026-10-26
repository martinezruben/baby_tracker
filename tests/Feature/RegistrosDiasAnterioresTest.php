<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Models\Caretaker;
use App\Models\DiaperLog;
use App\Models\Measurement;
use App\Models\SleepLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Anotar cosas de días anteriores: el formulario permite elegir el día y el servidor lo acepta (nunca el futuro). */
class RegistrosDiasAnterioresTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00'); // 08:00 local
        $this->mama = app(SetupFamily::class)->handle([
            'family_name' => 'Ruiz', 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => 'Sofía', 'baby_birth_date' => '2026-06-01',
        ]);
        $this->mama->family->settings->update(['timezone' => 'America/Santo_Domingo']);
        $this->actingAs($this->mama);
    }

    public function test_a_measurement_can_be_logged_for_a_day_weeks_ago(): void
    {
        $this->post('/registrar/medida', ['occurred_at' => '2026-10-13T12:00', 'type' => 'weight', 'value' => 5.2])->assertSessionHasNoErrors();

        $this->assertSame('2026-10-13', Measurement::firstOrFail()->measured_on->toDateString());
        $this->post('/registrar/medida', ['occurred_at' => '2026-11-04T12:00', 'type' => 'weight', 'value' => 5.4])->assertSessionHasErrors('occurred_at');
    }

    public function test_other_activities_accept_a_past_day_too(): void
    {
        $this->post('/registrar/panal', ['occurred_at' => '2026-10-30T21:15', 'type' => 'wet'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-31 01:15:00', DiaperLog::firstOrFail()->occurred_at->toDateTimeString());

        $this->post('/registrar/sueno', ['occurred_at' => '2026-10-29T20:00', 'in_progress' => false, 'ended_at' => '2026-10-30T06:30', 'type' => 'night_sleep'])->assertSessionHasNoErrors();
        $this->assertSame(630, SleepLog::firstOrFail()->duration_minutes);
    }

    public function test_the_time_block_offers_a_day_picker_and_date_only_mode_for_measurements(): void
    {
        $block = file_get_contents(base_path('resources/js/components/form/TimeBlock.jsx'));

        $this->assertStringContainsString('type="date"', $block);
        $this->assertStringContainsString('max={today}', $block); // nunca el futuro
        $this->assertStringContainsString('dateOnly', file_get_contents(base_path('resources/js/pages/Registrar/Medida.jsx')));
    }
}
