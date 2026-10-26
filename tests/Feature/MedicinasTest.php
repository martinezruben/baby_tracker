<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\PrescriptionStatus;
use App\Models\Caretaker;
use App\Models\Medicine;
use App\Models\MedicineLog;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MedicinasTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00'); // 08:00 local
        $this->mama = $this->newFamily('Ruiz', 'Sofía');
        $this->actingAs($this->mama);
    }

    private function newFamily(string $name, string $baby): Caretaker
    {
        $caretaker = app(SetupFamily::class)->handle([
            'family_name' => $name, 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => $baby, 'baby_birth_date' => '2026-06-01',
        ]);
        $caretaker->family->settings->update(['timezone' => 'America/Santo_Domingo']);

        return $caretaker;
    }

    private function treatment(array $overrides = []): array
    {
        return $overrides + ['new_medicine' => 'Amoxicilina', 'dose' => 5, 'unit' => 'ml', 'interval_hours' => 8, 'total_doses' => 3, 'starts_at' => '2026-11-03T08:00', 'instructions' => 'Con comida'];
    }

    public function test_a_treatment_can_start_without_an_appointment(): void
    {
        $this->post('/medicinas/tratamientos', $this->treatment())->assertRedirect()->assertSessionHasNoErrors();

        $rx = Prescription::firstOrFail();
        $this->assertNull($rx->appointment_id);
        $this->assertSame(3, $rx->doses()->count());
        $this->assertNotNull($rx->notification_rule_id);

        $this->get('/medicinas')->assertInertia(fn (Assert $page) => $page
            ->component('Medicinas')
            ->has('treatments', 1)
            ->where('treatments.0.medicine', 'Amoxicilina')
            ->where('treatments.0.given', 0)
            ->where('treatments.0.total_doses', 3)
            ->where('treatments.0.next.at', fn ($v) => str_starts_with($v, '2026-11-03T12:00'))
            ->where('treatments.0.appointment', null));
    }

    public function test_stopped_treatments_leave_the_ongoing_list(): void
    {
        $this->post('/medicinas/tratamientos', $this->treatment());
        $rx = Prescription::firstOrFail();

        $this->delete("/recetas/{$rx->id}")->assertRedirect();

        $this->assertSame(PrescriptionStatus::Cancelled, $rx->fresh()->status);
        $this->get('/medicinas')->assertInertia(fn (Assert $page) => $page->has('treatments', 0));
    }

    public function test_catalog_create_update_and_unique_names(): void
    {
        $this->post('/medicinas', ['name' => 'Paracetamol', 'typical_dose' => 2.5, 'unit' => 'ml', 'min_interval_hours' => 6, 'is_supplement' => false, 'notes' => 'Fiebre'])->assertRedirect();

        $medicine = Medicine::firstOrFail();
        $this->assertSame(360, $medicine->min_interval_minutes);
        $this->assertSame('2.50', $medicine->typical_dose);

        $this->post('/medicinas', ['name' => 'Paracetamol'])->assertSessionHasErrors('name');

        $this->put("/medicinas/{$medicine->id}", ['name' => 'Paracetamol', 'typical_dose' => 3, 'min_interval_hours' => 4.5])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(270, $medicine->fresh()->min_interval_minutes);

        $this->get('/medicinas')->assertInertia(fn (Assert $page) => $page->where('medicines.0.min_interval_hours', 4.5)->where('medicines.0.typical_dose', 3));
    }

    public function test_archiving_hides_a_medicine_from_forms_but_keeps_it_listed(): void
    {
        $medicine = Medicine::create(['family_id' => $this->mama->family_id, 'name' => 'Vitamina D', 'is_supplement' => true]);

        $this->post("/medicinas/{$medicine->id}/archivar")->assertRedirect();
        $this->assertFalse($medicine->fresh()->is_active);

        $this->get('/registrar/medicina')->assertInertia(fn (Assert $page) => $page->has('medicines', 0));
        $this->get('/medicinas')->assertInertia(fn (Assert $page) => $page->has('medicines', 1)->where('medicines.0.is_active', false));

        $this->post("/medicinas/{$medicine->id}/archivar");
        $this->assertTrue($medicine->fresh()->is_active);
    }

    public function test_log_form_receives_min_interval_and_last_dose_to_warn_about_early_doses(): void
    {
        $medicine = Medicine::create(['family_id' => $this->mama->family_id, 'name' => 'Ibuprofeno', 'min_interval_minutes' => 360]);
        $baby = $this->mama->family->babies()->first();
        MedicineLog::create(['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'caretaker_id' => $this->mama->id, 'medicine_id' => $medicine->id, 'occurred_at' => '2026-11-03 10:00:00', 'dose' => 2]);

        $this->get('/registrar/medicina')->assertInertia(fn (Assert $page) => $page
            ->where('medicines.0.min_interval_minutes', 360)
            ->where('medicines.0.last_given_at', fn ($v) => str_starts_with($v, '2026-11-03T10:00')));

        $this->get('/medicinas')->assertInertia(fn (Assert $page) => $page
            ->where('history.0.name', 'Ibuprofeno')->where('medicines.0.last_given_at', fn ($v) => str_starts_with($v, '2026-11-03T10:00')));
    }

    public function test_validation(): void
    {
        $this->post('/medicinas', ['name' => ''])->assertSessionHasErrors('name');
        $this->post('/medicinas', ['name' => 'X', 'min_interval_hours' => 0])->assertSessionHasErrors('min_interval_hours');
        $this->post('/medicinas/tratamientos', $this->treatment(['dose' => 0]))->assertSessionHasErrors('dose');
        $this->post('/medicinas/tratamientos', $this->treatment(['starts_at' => '2026-10-01T08:00']))->assertSessionHasErrors('starts_at');
        $this->assertSame(0, Prescription::count());
    }

    public function test_medicines_are_private_to_the_family(): void
    {
        $medicine = Medicine::create(['family_id' => $this->mama->family_id, 'name' => 'Secreta']);

        $this->actingAs($this->newFamily('Pérez', 'Luis'));

        $this->get('/medicinas')->assertInertia(fn (Assert $page) => $page->has('medicines', 0)->has('treatments', 0));
        $this->put("/medicinas/{$medicine->id}", ['name' => 'Hackeada'])->assertNotFound();
        $this->post("/medicinas/{$medicine->id}/archivar")->assertNotFound();
        $this->assertSame('Secreta', $medicine->fresh()->name);
    }

    public function test_login_required(): void
    {
        auth()->logout();
        $this->get('/medicinas')->assertRedirect();
        $this->post('/medicinas', ['name' => 'X'])->assertRedirect();
    }
}
