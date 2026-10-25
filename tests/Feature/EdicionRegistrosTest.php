<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\DiaperType;
use App\Enums\FeedType;
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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EdicionRegistrosTest extends TestCase
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

    private function owner(): array
    {
        $baby = $this->mama->family->babies()->first();

        return ['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'caretaker_id' => $this->mama->id];
    }

    public function test_edit_form_comes_filled_in_family_time(): void
    {
        $log = DiaperLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:30:00', 'type' => DiaperType::Dirty, 'blowout' => true]);

        $this->get("/registros/panal/{$log->id}/editar")->assertInertia(fn (Assert $page) => $page
            ->component('Registrar/Panal')
            ->where('record.id', $log->id)
            ->where('record.occurred_at', '2026-11-03T07:30')
            ->where('record.type', 'dirty')
            ->where('record.blowout', true));
    }

    public function test_diaper_is_updated_and_user_returns_to_the_log(): void
    {
        $log = DiaperLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:30:00', 'type' => DiaperType::Wet]);

        $this->put("/registros/panal/{$log->id}", ['occurred_at' => '2026-11-03T06:15', 'type' => 'both', 'cream_applied' => true, 'notes' => 'Corregido'])
            ->assertRedirect('/bitacora')->assertSessionHas('status');

        $log->refresh();
        $this->assertSame('2026-11-03 10:15:00', $log->occurred_at->toDateTimeString());
        $this->assertSame(DiaperType::Both, $log->type);
        $this->assertTrue($log->cream_applied);
        $this->assertSame('Corregido', $log->notes);
    }

    public function test_update_validates_like_create(): void
    {
        $log = DiaperLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:30:00', 'type' => DiaperType::Wet]);

        $this->put("/registros/panal/{$log->id}", ['occurred_at' => '2026-11-04T07:15', 'type' => 'wet'])->assertSessionHasErrors('occurred_at');
        $this->put("/registros/panal/{$log->id}", ['occurred_at' => '2026-11-03T07:15', 'type' => 'nope'])->assertSessionHasErrors('type');
        $this->assertSame(DiaperType::Wet, $log->fresh()->type);
    }

    public function test_bottle_note_medicine_and_measurement_can_be_edited(): void
    {
        $bottle = FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:00:00', 'started_at' => '2026-11-03 11:00:00', 'type' => FeedType::Bottle, 'amount' => 90, 'unit' => 'ml']);
        $this->put("/registros/biberon/{$bottle->id}", ['occurred_at' => '2026-11-03T07:00', 'amount' => 120, 'unit' => 'ml', 'bottle_type' => 'formula'])->assertRedirect('/bitacora');
        $this->assertSame('120.00', $bottle->fresh()->amount);

        $note = Note::create($this->owner() + ['occurred_at' => '2026-11-03 11:00:00', 'content' => 'a']);
        $this->put("/registros/nota/{$note->id}", ['occurred_at' => '2026-11-03T07:00', 'content' => 'b'])->assertRedirect('/bitacora');
        $this->assertSame('b', $note->fresh()->content);

        $medicine = Medicine::create(['family_id' => $this->mama->family_id, 'name' => 'Paracetamol']);
        $log = MedicineLog::create($this->owner() + ['medicine_id' => $medicine->id, 'occurred_at' => '2026-11-03 11:00:00', 'dose' => 2, 'unit' => 'ml']);
        $this->put("/registros/medicina/{$log->id}", ['occurred_at' => '2026-11-03T07:00', 'medicine_id' => $medicine->id, 'dose' => 3, 'unit' => 'ml'])->assertRedirect('/bitacora');
        $this->assertSame('3.00', $log->fresh()->dose);

        $m = Measurement::create($this->owner() + ['measured_on' => '2026-11-02', 'type' => 'weight', 'value' => 5, 'unit' => 'kg']);
        $this->put("/registros/medida/{$m->id}", ['occurred_at' => '2026-11-02T07:00', 'type' => 'weight', 'value' => 5.4])->assertRedirect('/bitacora');
        $this->assertSame('5.40', $m->fresh()->value);
    }

    public function test_sleep_can_be_closed_from_edit_but_not_reopened_over_another_open_one(): void
    {
        $open = SleepLog::create($this->owner() + ['started_at' => '2026-11-03 09:00:00', 'type' => 'nap']);

        $this->put("/registros/sueno/{$open->id}", ['occurred_at' => '2026-11-03T05:00', 'in_progress' => false, 'ended_at' => '2026-11-03T06:30', 'type' => 'nap'])->assertRedirect('/bitacora');
        $this->assertSame(90, $open->fresh()->duration_minutes);

        SleepLog::create($this->owner() + ['started_at' => '2026-11-03 11:00:00', 'type' => 'nap']);
        $this->put("/registros/sueno/{$open->id}", ['occurred_at' => '2026-11-03T05:00', 'in_progress' => true, 'type' => 'nap'])->assertSessionHasErrors('in_progress');
    }

    public function test_saved_breast_feed_can_be_corrected(): void
    {
        $log = FeedLog::create($this->owner() + [
            'occurred_at' => '2026-11-03 10:00:00', 'started_at' => '2026-11-03 10:00:00', 'type' => FeedType::Breast,
            'left_seconds' => 600, 'right_seconds' => 300, 'duration_seconds' => 900,
        ]);

        $this->get("/registros/pecho/{$log->id}/editar")->assertInertia(fn (Assert $page) => $page
            ->component('Registrar/PechoEditar')->where('record.left_minutes', 10)->where('record.right_minutes', 5));

        $this->put("/registros/pecho/{$log->id}", ['occurred_at' => '2026-11-03T06:00', 'left_minutes' => 12, 'right_minutes' => 0])->assertRedirect('/bitacora');

        $log->refresh();
        $this->assertSame(720, $log->duration_seconds);
        $this->assertSame('left', $log->side->value);
        $this->put("/registros/pecho/{$log->id}", ['occurred_at' => '2026-11-03T06:00', 'left_minutes' => 0, 'right_minutes' => 0])->assertSessionHasErrors('right_minutes');
    }

    public function test_other_family_cannot_edit_a_log(): void
    {
        $log = DiaperLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:30:00', 'type' => DiaperType::Wet]);

        $this->actingAs($this->newFamily('Pérez'));

        $this->get("/registros/panal/{$log->id}/editar")->assertNotFound();
        $this->put("/registros/panal/{$log->id}", ['occurred_at' => '2026-11-03T07:15', 'type' => 'dirty'])->assertNotFound();
        $this->assertSame(DiaperType::Wet, $log->fresh()->type);
    }
}
