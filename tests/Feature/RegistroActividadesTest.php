<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\BreastSide;
use App\Enums\DiaperType;
use App\Enums\FeedType;
use App\Enums\MeasurementType;
use App\Models\ActiveBreastFeed;
use App\Models\Baby;
use App\Models\Caretaker;
use App\Models\DiaperLog;
use App\Models\FeedLog;
use App\Models\Measurement;
use App\Models\Medicine;
use App\Models\MedicineLog;
use App\Models\Note;
use App\Models\SleepLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RegistroActividadesTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00'); // 08:00 en Santo Domingo
        $this->mama = $this->newFamily('Ruiz');
        $this->actingAs($this->mama);
    }

    private function newFamily(string $name): Caretaker
    {
        $caretaker = app(SetupFamily::class)->handle([
            'family_name' => $name, 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => 'Sofía', 'baby_birth_date' => '2026-06-01',
        ]);
        $caretaker->family->settings->update(['timezone' => 'America/Santo_Domingo']);

        return $caretaker;
    }

    private function baby(): Baby
    {
        return $this->mama->family->babies()->first();
    }

    // ---- Guardar ------------------------------------------------------------

    public function test_diaper_is_saved_in_utc_and_offers_undo(): void
    {
        $this->post('/registrar/panal', ['occurred_at' => '2026-11-03T07:45', 'type' => 'wet', 'cream_applied' => true, 'notes' => 'Algo de rojez'])
            ->assertRedirect('/')
            ->assertSessionHas('undo.message', 'Pañal guardado');

        $log = DiaperLog::firstOrFail();
        $this->assertTrue($log->occurred_at->equalTo('2026-11-03 11:45:00')); // 07:45 local = 11:45 UTC
        $this->assertSame(DiaperType::Wet, $log->type);
        $this->assertTrue($log->cream_applied);
        $this->assertSame($this->mama->id, $log->caretaker_id);
        $this->assertSame($this->baby()->id, $log->baby_id);
        $this->assertSame(route('records.destroy', ['type' => 'panal', 'id' => $log->id]), session('undo.url'));
    }

    public function test_bottle_is_saved_as_a_bottle_feed(): void
    {
        $this->post('/registrar/biberon', ['occurred_at' => '2026-11-03T08:00', 'amount' => 120, 'unit' => 'ml', 'bottle_type' => 'formula'])
            ->assertRedirect('/');

        $log = FeedLog::firstOrFail();
        $this->assertSame(FeedType::Bottle, $log->type);
        $this->assertSame('120.00', $log->amount);
        $this->assertSame('formula', $log->bottle_type);
    }

    public function test_sleep_can_be_saved_closed_or_in_progress(): void
    {
        $this->post('/registrar/sueno', ['occurred_at' => '2026-11-03T01:00', 'in_progress' => false, 'ended_at' => '2026-11-03T02:30', 'type' => 'night_sleep'])
            ->assertRedirect('/');
        $closed = SleepLog::firstOrFail();
        $this->assertSame(90, $closed->duration_minutes);

        $this->post('/registrar/sueno', ['occurred_at' => '2026-11-03T07:30', 'in_progress' => true, 'type' => 'nap'])->assertRedirect('/');
        $this->assertNull(SleepLog::latest('started_at')->first()->ended_at);

        // Solo puede haber un sueño abierto a la vez.
        $this->post('/registrar/sueno', ['occurred_at' => '2026-11-03T07:40', 'in_progress' => true, 'type' => 'nap'])
            ->assertSessionHasErrors('in_progress');
        $this->assertSame(2, SleepLog::count());
    }

    public function test_sleep_end_must_not_be_before_the_start(): void
    {
        $this->post('/registrar/sueno', ['occurred_at' => '2026-11-03T05:00', 'in_progress' => false, 'ended_at' => '2026-11-03T04:00', 'type' => 'nap'])
            ->assertSessionHasErrors('ended_at');
        $this->post('/registrar/sueno', ['occurred_at' => '2026-11-03T05:00', 'in_progress' => false, 'type' => 'nap'])
            ->assertSessionHasErrors('ended_at');
    }

    public function test_medicine_from_the_catalog_or_a_new_one(): void
    {
        $vit = Medicine::create(['family_id' => $this->mama->family_id, 'name' => 'Vitamina D', 'unit' => 'gotas']);

        $this->post('/registrar/medicina', ['occurred_at' => '2026-11-03T07:00', 'medicine_id' => $vit->id, 'dose' => 1])->assertRedirect('/');
        $this->post('/registrar/medicina', ['occurred_at' => '2026-11-03T07:10', 'new_medicine' => 'Paracetamol', 'dose' => 2.5, 'unit' => 'ml'])->assertRedirect('/');
        $this->post('/registrar/medicina', ['occurred_at' => '2026-11-03T07:20', 'new_medicine' => ' Paracetamol ', 'dose' => 2.5, 'unit' => 'ml'])->assertRedirect('/');

        $this->assertSame(['Paracetamol', 'Vitamina D'], Medicine::orderBy('name')->pluck('name')->all()); // sin duplicar
        $this->assertSame(3, MedicineLog::count());
        $this->assertSame('gotas', MedicineLog::oldest('id')->first()->unit); // hereda la unidad de la medicina
    }

    public function test_medicine_of_another_family_is_rejected(): void
    {
        $stranger = $this->newFamily('Otra');
        $theirs = Medicine::create(['family_id' => $stranger->family_id, 'name' => 'Ajena']);

        $this->post('/registrar/medicina', ['occurred_at' => '2026-11-03T07:00', 'medicine_id' => $theirs->id, 'dose' => 1])
            ->assertSessionHasErrors('medicine_id');
        $this->post('/registrar/medicina', ['occurred_at' => '2026-11-03T07:00', 'dose' => 1])
            ->assertSessionHasErrors(['medicine_id', 'new_medicine']);
    }

    public function test_measurement_uses_the_units_from_settings(): void
    {
        $this->mama->family->settings->update(['weight_unit' => 'lb']);

        $this->post('/registrar/medida', ['occurred_at' => '2026-11-03T07:00', 'type' => 'weight', 'value' => 13.4])->assertRedirect('/');
        $this->post('/registrar/medida', ['occurred_at' => '2026-11-03T07:00', 'type' => 'height', 'value' => 61])->assertRedirect('/');

        $this->assertSame('lb', Measurement::where('type', MeasurementType::Weight)->first()->unit);
        $this->assertSame('cm', Measurement::where('type', MeasurementType::Height)->first()->unit);
        $this->assertSame('2026-11-03', Measurement::first()->measured_on->toDateString());
    }

    public function test_note_is_saved(): void
    {
        $this->post('/registrar/nota', ['occurred_at' => '2026-11-03T07:00', 'content' => 'Primera sonrisa'])->assertRedirect('/');

        $this->assertSame('Primera sonrisa', Note::firstOrFail()->content);
    }

    public function test_future_times_and_invalid_values_are_rejected(): void
    {
        $this->post('/registrar/panal', ['occurred_at' => '2026-11-03T09:00', 'type' => 'wet'])->assertSessionHasErrors('occurred_at');
        $this->post('/registrar/panal', ['occurred_at' => '2026-11-03T07:00', 'type' => 'otro'])->assertSessionHasErrors('type');
        $this->post('/registrar/panal', ['occurred_at' => 'ayer', 'type' => 'wet'])->assertSessionHasErrors('occurred_at');
        $this->post('/registrar/biberon', ['occurred_at' => '2026-11-03T07:00', 'amount' => 0, 'unit' => 'ml'])->assertSessionHasErrors('amount');
        $this->post('/registrar/biberon', ['occurred_at' => '2026-11-03T07:00', 'amount' => 90, 'unit' => 'galones'])->assertSessionHasErrors('unit');
        $this->post('/registrar/inventado', [])->assertNotFound();

        // Hasta 5 minutos de margen por el reloj del teléfono.
        $this->post('/registrar/panal', ['occurred_at' => '2026-11-03T08:04', 'type' => 'wet'])->assertSessionHasNoErrors();
        $this->assertSame(1, DiaperLog::count());
    }

    // ---- Deshacer ---------------------------------------------------------------

    public function test_undo_soft_deletes_the_record(): void
    {
        $this->post('/registrar/panal', ['occurred_at' => '2026-11-03T07:45', 'type' => 'wet']);
        $log = DiaperLog::firstOrFail();

        $this->delete(session('undo.url'))->assertRedirect();

        $this->assertSoftDeleted($log);
    }

    public function test_a_family_cannot_undo_someone_elses_record(): void
    {
        $this->post('/registrar/panal', ['occurred_at' => '2026-11-03T07:45', 'type' => 'wet']);
        $log = DiaperLog::firstOrFail();

        $this->actingAs($this->newFamily('Otra'))
            ->delete("/registros/panal/{$log->id}")->assertNotFound();

        $this->assertNotSoftDeleted($log);
    }

    // ---- Repetir lo último -----------------------------------------------------------

    public function test_repeat_copies_the_last_record_with_the_current_time(): void
    {
        $this->post('/registrar/panal', ['occurred_at' => '2026-11-03T05:00', 'type' => 'dirty', 'cream_applied' => true]);
        $this->post('/registrar/biberon', ['occurred_at' => '2026-11-03T05:00', 'amount' => 120, 'unit' => 'ml']);
        $vit = Medicine::create(['family_id' => $this->mama->family_id, 'name' => 'Vitamina D']);
        $this->post('/registrar/medicina', ['occurred_at' => '2026-11-03T05:00', 'medicine_id' => $vit->id, 'dose' => 1, 'unit' => 'gota']);

        foreach (['panal', 'biberon', 'medicina'] as $type) {
            $this->post("/registrar/{$type}/repetir")->assertRedirect('/')->assertSessionHas('undo');
        }

        $diaper = DiaperLog::latest('id')->first();
        $this->assertSame(DiaperType::Dirty, $diaper->type);
        $this->assertTrue($diaper->cream_applied);
        $this->assertTrue($diaper->occurred_at->equalTo('2026-11-03 12:00:00')); // ahora
        $this->assertSame('120.00', FeedLog::latest('id')->first()->amount);
        $this->assertSame('1.00', MedicineLog::latest('id')->first()->dose);
        $this->assertSame(2, DiaperLog::count());
    }

    public function test_repeat_is_not_available_without_a_previous_record_or_for_other_types(): void
    {
        $this->post('/registrar/panal/repetir')->assertNotFound();
        $this->post('/registrar/nota/repetir')->assertNotFound();
    }

    // ---- Sueño en curso -----------------------------------------------------------------

    public function test_wake_up_closes_the_sleep_and_undo_reopens_it(): void
    {
        $this->post('/registrar/sueno', ['occurred_at' => '2026-11-03T06:30', 'in_progress' => true, 'type' => 'nap']);
        $sleep = SleepLog::firstOrFail();

        $this->patch("/sueno/{$sleep->id}/despertar")->assertRedirect()->assertSessionHas('undo.method', 'patch');

        $this->assertTrue($sleep->fresh()->ended_at->equalTo('2026-11-03 12:00:00'));
        $this->assertSame(90, $sleep->fresh()->duration_minutes); // 06:30 -> 08:00 local

        $this->patch(session('undo.url'))->assertRedirect();
        $this->assertNull($sleep->fresh()->ended_at);
        $this->assertNull($sleep->fresh()->duration_minutes);
    }

    public function test_only_the_own_family_can_wake_a_sleep(): void
    {
        $this->post('/registrar/sueno', ['occurred_at' => '2026-11-03T06:30', 'in_progress' => true, 'type' => 'nap']);
        $sleep = SleepLog::firstOrFail();

        $this->actingAs($this->newFamily('Otra'))->patch("/sueno/{$sleep->id}/despertar")->assertNotFound();
        $this->assertNull($sleep->fresh()->ended_at);
    }

    // ---- Lactancia ------------------------------------------------------------------------

    public function test_breastfeed_timer_tracks_each_side_pauses_and_saves_the_feed(): void
    {
        $this->post('/registrar/pecho', ['side' => 'left'])->assertRedirect('/');
        $this->assertSame(BreastSide::Left, ActiveBreastFeed::firstOrFail()->active_side);

        Carbon::setTestNow('2026-11-03 12:05:00'); // 5 min izquierdo
        $this->patch('/pecho/cambiar')->assertRedirect();

        Carbon::setTestNow('2026-11-03 12:07:00'); // 2 min derecho, luego pausa
        $this->patch('/pecho/pausar');

        Carbon::setTestNow('2026-11-03 12:08:00'); // 1 min en pausa, luego reanuda
        $this->patch('/pecho/reanudar');

        Carbon::setTestNow('2026-11-03 12:11:00'); // 3 min más a la derecha
        $this->patch('/pecho/finalizar')->assertRedirect()->assertSessionHas('undo.message', 'Toma de pecho guardada');

        $this->assertDatabaseCount('active_breast_feeds', 0);
        $feed = FeedLog::firstOrFail();
        $this->assertSame(FeedType::Breast, $feed->type);
        $this->assertSame(300, $feed->left_seconds);
        $this->assertSame(300, $feed->right_seconds); // 2 min + 3 min
        $this->assertSame(600, $feed->duration_seconds);
        $this->assertSame(60, $feed->pause_seconds);
        $this->assertNull($feed->side); // usó los dos lados
        $this->assertTrue($feed->started_at->equalTo('2026-11-03 12:00:00'));
        $this->assertTrue($feed->ended_at->equalTo('2026-11-03 12:11:00'));

        // El aviso de deshacer apunta al registro de la toma.
        $this->delete(session('undo.url'));
        $this->assertSoftDeleted($feed);
    }

    public function test_a_single_side_feed_records_that_side(): void
    {
        $this->post('/registrar/pecho', ['side' => 'right']);
        Carbon::setTestNow('2026-11-03 12:09:00');
        $this->patch('/pecho/finalizar');

        $feed = FeedLog::firstOrFail();
        $this->assertSame(BreastSide::Right, $feed->side);
        $this->assertSame(540, $feed->duration_seconds);
    }

    public function test_only_one_breastfeed_at_a_time_and_it_can_be_discarded(): void
    {
        $this->post('/registrar/pecho', ['side' => 'left']);
        $this->post('/registrar/pecho', ['side' => 'right'])->assertSessionHasErrors('side');
        $this->assertSame(1, ActiveBreastFeed::count());

        $this->delete('/pecho')->assertRedirect();
        $this->assertSame(0, ActiveBreastFeed::count());
        $this->assertSame(0, FeedLog::count());

        $this->patch('/pecho/pausar')->assertNotFound();
        $this->patch('/pecho/explotar')->assertNotFound();
    }

    public function test_the_timer_does_not_leak_between_families(): void
    {
        $this->post('/registrar/pecho', ['side' => 'left']);

        $this->actingAs($this->newFamily('Otra'))->patch('/pecho/pausar')->assertNotFound();
        $this->assertFalse(ActiveBreastFeed::first()->is_paused);
    }
}
