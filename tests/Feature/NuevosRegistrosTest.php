<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\FoodEnjoyment;
use App\Enums\Mood;
use App\Enums\PlayType;
use App\Models\BathLog;
use App\Models\Caretaker;
use App\Models\Food;
use App\Models\FoodLog;
use App\Models\MoodLog;
use App\Models\PlayLog;
use App\Models\PumpLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NuevosRegistrosTest extends TestCase
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

    public function test_solids_create_the_food_and_can_reuse_it(): void
    {
        $this->post('/registrar/solidos', ['occurred_at' => '2026-11-03T07:30', 'new_food' => ' Aguacate ', 'amount' => 30, 'unit' => 'g', 'enjoyment' => 'loved'])
            ->assertRedirect('/')->assertSessionHas('undo');

        $food = Food::firstWhere('name', 'Aguacate');
        $log = FoodLog::firstOrFail();
        $this->assertSame($food->id, $log->food_id);
        $this->assertSame(FoodEnjoyment::Loved, $log->enjoyment);
        $this->assertSame('2026-11-03 11:30:00', $log->occurred_at->toDateTimeString());

        $this->post('/registrar/solidos', ['occurred_at' => '2026-11-03T07:40', 'food_id' => $food->id, 'had_reaction' => true, 'reaction_notes' => 'Sarpullido'])->assertRedirect('/');
        $this->assertSame(1, Food::count());
        $this->assertSame('Sarpullido', FoodLog::latest('id')->first()->reaction_notes);

        $this->get('/registrar/solidos')->assertInertia(fn (Assert $page) => $page->component('Registrar/Solidos')->has('foods', 1));
    }

    public function test_solids_require_a_food_and_ignore_notes_without_reaction(): void
    {
        $this->post('/registrar/solidos', ['occurred_at' => '2026-11-03T07:30'])->assertSessionHasErrors(['food_id', 'new_food']);

        $food = Food::create(['family_id' => $this->mama->family_id, 'name' => 'Papa']);
        $this->post('/registrar/solidos', ['occurred_at' => '2026-11-03T07:30', 'food_id' => $food->id, 'reaction_notes' => 'x']);
        $this->assertNull(FoodLog::first()->reaction_notes);
    }

    public function test_pumping_totals_both_sides_and_needs_some_milk(): void
    {
        $this->post('/registrar/extraccion', ['occurred_at' => '2026-11-03T06:00', 'left_amount' => 60, 'right_amount' => 45, 'unit' => 'ml', 'duration_minutes' => 20])->assertRedirect('/');

        $log = PumpLog::firstOrFail();
        $this->assertSame('105.00', $log->total_amount);
        $this->assertSame('2026-11-03 10:20:00', $log->ended_at->toDateTimeString());

        $this->post('/registrar/extraccion', ['occurred_at' => '2026-11-03T06:00', 'left_amount' => 0, 'right_amount' => 0, 'unit' => 'ml'])->assertSessionHasErrors('right_amount');
    }

    public function test_bath_play_and_mood_are_saved_and_can_be_edited(): void
    {
        $this->post('/registrar/bano', ['occurred_at' => '2026-11-03T07:00', 'bath_type' => 'sponge', 'soap_used' => true])->assertRedirect('/');
        $bath = BathLog::firstOrFail();
        $this->assertTrue($bath->soap_used);

        $this->post('/registrar/juego', ['occurred_at' => '2026-11-03T07:00', 'type' => 'tummy_time', 'duration_minutes' => 15])->assertRedirect('/');
        $play = PlayLog::firstOrFail();
        $this->assertSame(PlayType::TummyTime, $play->type);
        $this->assertSame('2026-11-03 11:15:00', $play->ended_at->toDateTimeString());

        $this->post('/registrar/animo', ['occurred_at' => '2026-11-03T07:00', 'mood' => 'fussy', 'intensity' => 4])->assertRedirect('/');
        $mood = MoodLog::firstOrFail();
        $this->assertSame(Mood::Fussy, $mood->mood);

        $this->put("/registros/animo/{$mood->id}", ['occurred_at' => '2026-11-03T07:05', 'mood' => 'calm'])->assertRedirect('/bitacora');
        $this->assertSame(Mood::Calm, $mood->fresh()->mood);
        $this->put("/registros/juego/{$play->id}", ['occurred_at' => '2026-11-03T07:00', 'type' => 'walk', 'duration_minutes' => 30])->assertRedirect('/bitacora');
        $this->assertSame(30, $play->fresh()->duration_minutes);

        $this->post('/registrar/animo', ['occurred_at' => '2026-11-03T07:00', 'mood' => 'sleepy'])->assertSessionHasErrors('mood');
        $this->post('/registrar/juego', ['occurred_at' => '2026-11-03T07:00', 'type' => 'tummy_time', 'duration_minutes' => 0])->assertSessionHasErrors('duration_minutes');
    }

    public function test_new_types_show_up_in_the_log_and_the_calendar_counts_solids(): void
    {
        $food = Food::create(['family_id' => $this->mama->family_id, 'name' => 'Papa']);
        $this->post('/registrar/solidos', ['occurred_at' => '2026-11-03T07:30', 'food_id' => $food->id]);
        $this->post('/registrar/bano', ['occurred_at' => '2026-11-03T07:00']);
        $this->post('/registrar/extraccion', ['occurred_at' => '2026-11-03T06:00', 'left_amount' => 30, 'right_amount' => 0, 'unit' => 'ml']);
        $this->post('/registrar/juego', ['occurred_at' => '2026-11-03T05:00', 'type' => 'walk']);
        $this->post('/registrar/animo', ['occurred_at' => '2026-11-03T04:00', 'mood' => 'happy']);

        $this->get('/bitacora')->assertInertia(fn (Assert $page) => $page
            ->has('entries', 5)
            ->where('entries.0.type', 'solidos')
            ->where('entries.0.data.food', 'Papa')
            ->where('entries.1.type', 'bano')
            ->where('entries.2.type', 'extraccion'));

        $this->get('/bitacora?tipo=animo')->assertInertia(fn (Assert $page) => $page->has('entries', 1)->where('entries.0.data.mood', 'happy'));
        $days = $this->get('/calendario?mes=2026-11')->viewData('page')['props']['data']['days'];
        $this->assertSame(1, $days['2026-11-03']['counts']['feed']);
    }

    public function test_undo_works_for_the_new_types(): void
    {
        $this->post('/registrar/bano', ['occurred_at' => '2026-11-03T07:00']);
        $bath = BathLog::firstOrFail();

        $this->delete("/registros/bano/{$bath->id}")->assertRedirect();
        $this->assertSoftDeleted($bath);
    }
}
