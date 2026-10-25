<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Actions\SyncVaccineReminders;
use App\Enums\NotificationEventType;
use App\Models\Caretaker;
use App\Models\NotificationRule;
use App\Models\NotificationTarget;
use App\Models\Vaccination;
use App\Models\VaccineDose;
use App\Support\VaccineScheduleImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VacunasTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    private Caretaker $papa;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-11-03 08:00 en Santo Domingo. La bebé nació el 2026-09-03: hoy tiene exactamente 61 días.
        Carbon::setTestNow('2026-11-03 12:00:00');
        $this->mama = $this->newFamily('Ruiz');
        $this->papa = $this->mama->family->caretakers()->create(['login_id' => 'papa', 'name' => 'Papá', 'pin' => '246802']);
        $this->mama->family->babies()->first()->update(['birth_date' => '2026-09-03']);
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

    private function dose(string $name, int $days, ?string $label = null, ?Caretaker $who = null): VaccineDose
    {
        return VaccineDose::create(['family_id' => ($who ?? $this->mama)->family_id, 'vaccine_name' => $name, 'dose_label' => $label, 'age_days' => $days]);
    }

    private function statuses(): array
    {
        return collect($this->get('/vacunas')->viewData('page')['props']['doses'])->pluck('status', 'name')->all();
    }

    // ---- Estado de cada dosis -----------------------------------------------------------

    public function test_each_dose_is_applied_overdue_soon_or_later_from_the_birth_date(): void
    {
        $this->dose('Al nacer', 0);          // vence el 2026-09-03: vencida
        $this->dose('A los 2 meses', 61);    // vence hoy: próxima (0 días)
        $this->dose('A los 3 meses', 120);   // vence el 2027-01-01 (59 días): próxima
        $this->dose('A los 6 meses', 182);   // 2027-03-04 (121 días): más adelante
        $applied = $this->dose('Ya puesta', 30);
        $baby = $this->mama->family->babies()->first();
        Vaccination::create(['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'vaccine_dose_id' => $applied->id, 'vaccine_name' => 'Ya puesta', 'administered_at' => '2026-10-05 13:00:00']);

        $this->assertEquals(
            ['Al nacer' => 'overdue', 'A los 2 meses' => 'soon', 'A los 3 meses' => 'soon', 'A los 6 meses' => 'later', 'Ya puesta' => 'applied'],
            $this->statuses(),
        );

        $this->get('/vacunas')->assertInertia(fn (Assert $page) => $page
            ->component('Vacunas/Index')
            ->where('counts', ['total' => 5, 'applied' => 1, 'overdue' => 1])
            ->where('doses.0.name', 'Al nacer')
            ->where('doses.0.due_on', '2026-09-03')
            ->where('doses.2.days_left', 0)
            ->where('has_schedule', true));
    }

    public function test_the_schedule_belongs_to_the_family_and_applications_to_each_baby(): void
    {
        $this->dose('BCG', 0);
        $mateo = $this->mama->family->babies()->create(['first_name' => 'Mateo', 'birth_date' => '2026-10-20']);
        $first = $this->mama->family->babies()->first();
        Vaccination::create(['family_id' => $first->family_id, 'baby_id' => $first->id, 'vaccine_dose_id' => VaccineDose::first()->id, 'vaccine_name' => 'BCG', 'administered_at' => '2026-09-04 13:00:00']);

        $this->assertSame(['BCG' => 'applied'], $this->statuses());

        $this->post("/bebes/{$mateo->id}/seleccionar");
        $this->assertSame(['BCG' => 'overdue'], $this->statuses()); // a Mateo no se le ha puesto

        $stranger = $this->newFamily('Otra');
        $this->actingAs($stranger);
        $this->assertSame([], $this->statuses());
    }

    // ---- Esquema editable ------------------------------------------------------------------

    public function test_doses_can_be_added_in_months_and_deleted_and_the_schedule_can_be_renamed(): void
    {
        $this->post('/vacunas/esquema/dosis', ['vaccine_name' => ' Vacuna A ', 'dose_label' => '2.ª dosis', 'age_months' => 1.5, 'notes' => 'Sin ayuno'])->assertSessionHasNoErrors();

        $dose = VaccineDose::firstOrFail();
        $this->assertSame('Vacuna A', $dose->vaccine_name);
        $this->assertSame(46, $dose->age_days); // 1,5 meses
        $this->post('/vacunas/esquema/dosis', ['vaccine_name' => 'Al nacer', 'age_months' => 0])->assertSessionHasNoErrors();
        $this->assertSame(0, VaccineDose::orderBy('id')->get()[1]->age_days);

        $this->post('/vacunas/esquema/dosis', ['vaccine_name' => '', 'age_months' => 2])->assertSessionHasErrors('vaccine_name');
        $this->post('/vacunas/esquema/dosis', ['vaccine_name' => 'X', 'age_months' => -1])->assertSessionHasErrors('age_months');

        $this->patch('/vacunas/esquema/nombre', ['name' => 'Cartilla de Sofía'])->assertSessionHasNoErrors();
        $this->assertSame('Cartilla de Sofía', $this->mama->family->settings->fresh()->vaccine_schedule_name);

        $this->delete("/vacunas/esquema/dosis/{$dose->id}")->assertRedirect();
        $this->assertSame(1, VaccineDose::count());

        $this->get('/vacunas/esquema')->assertInertia(fn (Assert $page) => $page->component('Vacunas/Esquema')->where('name', 'Cartilla de Sofía')->has('doses', 1));
    }

    public function test_the_paste_import_understands_units_separators_and_comments(): void
    {
        $parsed = VaccineScheduleImport::parse(<<<'TXT'
            # cartilla
            Vacuna A; Única; al nacer; Una sola vez
            Vacuna B | 1.ª dosis | 2 meses
            Vacuna B, 2.ª dosis, 4
            Vacuna C	refuerzo	6 semanas
            Vacuna D;;1 año;
            Vacuna E;;10 días
            Vacuna F;;1,5
            TXT);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame(
            [0, 61, 122, 42, 365, 10, 46],
            array_column($parsed['rows'], 'age_days'),
        );
        $this->assertSame('Una sola vez', $parsed['rows'][0]['notes']);
        $this->assertNull($parsed['rows'][4]['dose_label']);
        $this->assertSame('Vacuna C', $parsed['rows'][3]['vaccine_name']);
    }

    public function test_import_is_all_or_nothing_and_reports_the_bad_lines(): void
    {
        $this->post('/vacunas/esquema/importar', ['text' => "Vacuna A; ; 2 meses\n;;3 meses\nVacuna C; ; pronto", 'replace' => false])
            ->assertSessionHasErrors('text');

        $this->assertSame(0, VaccineDose::count());
        $message = session('errors')->first('text');
        $this->assertStringContainsString('Línea 2', $message);
        $this->assertStringContainsString('Línea 3', $message);
        $this->assertStringNotContainsString('Línea 1', $message);

        $this->post('/vacunas/esquema/importar', ['text' => "  \n# solo comentarios", 'replace' => false])->assertSessionHasErrors('text');
    }

    public function test_import_can_add_or_replace_the_schedule(): void
    {
        $this->dose('Vieja', 10);

        $this->post('/vacunas/esquema/importar', ['text' => "Nueva 1; ; 2 meses\nNueva 2; ; 4 meses", 'replace' => false])->assertSessionHasNoErrors();
        $this->assertSame(3, VaccineDose::count());

        $this->post('/vacunas/esquema/importar', ['text' => 'Solo esta; ; 0', 'replace' => true]);
        $this->assertSame(['Solo esta'], VaccineDose::pluck('vaccine_name')->all());
    }

    // ---- Registrar una aplicación -----------------------------------------------------------

    public function test_applying_a_scheduled_dose_links_it_copies_its_name_and_offers_undo(): void
    {
        $dose = $this->dose('Vacuna A', 61, '1.ª dosis');

        $this->post('/vacunas', [
            'vaccine_dose_id' => $dose->id, 'administered_at' => '2026-11-03T07:30', 'lot' => 'AB123', 'place' => 'Clínica', 'administered_by' => 'Enf. Ramírez', 'notes' => 'Sin reacción',
        ])->assertRedirect(route('vaccines.index'))->assertSessionHas('undo.message', 'Vacuna registrada');

        $v = Vaccination::firstOrFail();
        $this->assertSame($dose->id, $v->vaccine_dose_id);
        $this->assertSame('Vacuna A', $v->vaccine_name);
        $this->assertSame('1.ª dosis', $v->dose_label);
        $this->assertTrue($v->administered_at->equalTo('2026-11-03 11:30:00'));
        $this->assertSame($this->mama->id, $v->caretaker_id);
        $this->assertSame(['Vacuna A' => 'applied'], $this->statuses());
    }

    public function test_an_off_schedule_vaccine_can_be_logged_and_a_dose_cannot_be_applied_twice(): void
    {
        $dose = $this->dose('Vacuna A', 61);

        $this->post('/vacunas', ['vaccine_name' => 'Gripe (privada)', 'dose_label' => 'Anual', 'administered_at' => '2026-11-03T07:30'])->assertSessionHasNoErrors();
        $this->assertNull(Vaccination::first()->vaccine_dose_id);
        $this->get('/vacunas')->assertInertia(fn (Assert $page) => $page->where('extra.0.name', 'Gripe (privada)')->where('counts.applied', 0));

        $this->post('/vacunas', ['vaccine_dose_id' => $dose->id, 'administered_at' => '2026-11-03T07:30'])->assertSessionHasNoErrors();
        $this->post('/vacunas', ['vaccine_dose_id' => $dose->id, 'administered_at' => '2026-11-03T07:31'])->assertSessionHasErrors('vaccine_dose_id');
        $this->assertSame(2, Vaccination::count());
    }

    public function test_application_validation(): void
    {
        $dose = $this->dose('Vacuna A', 61);
        $stranger = $this->newFamily('Otra');
        $theirs = $this->dose('Ajena', 0, null, $stranger);

        $this->post('/vacunas', ['administered_at' => '2026-11-03T07:30'])->assertSessionHasErrors('vaccine_name');
        $this->post('/vacunas', ['vaccine_dose_id' => $dose->id, 'administered_at' => '2026-11-03T09:00'])->assertSessionHasErrors('administered_at'); // futuro
        $this->post('/vacunas', ['vaccine_dose_id' => $dose->id, 'administered_at' => 'ayer'])->assertSessionHasErrors('administered_at');
        $this->post('/vacunas', ['vaccine_dose_id' => $theirs->id, 'administered_at' => '2026-11-03T07:30'])->assertSessionHasErrors('vaccine_dose_id');
        $this->assertSame(0, Vaccination::count());
    }

    public function test_deleting_an_entry_can_be_restored_and_stays_inside_the_family(): void
    {
        $dose = $this->dose('Vacuna A', 61);
        $this->post('/vacunas', ['vaccine_dose_id' => $dose->id, 'administered_at' => '2026-11-03T07:30']);
        $v = Vaccination::firstOrFail();

        $this->delete("/vacunas/{$v->id}")->assertSessionHas('undo.method', 'post');
        $restoreUrl = session('undo.url');
        $this->assertSoftDeleted($v);
        $this->assertSame(['Vacuna A' => 'soon'], $this->statuses());

        $this->post($restoreUrl)->assertRedirect(route('vaccines.index'));
        $this->assertNotSoftDeleted($v);
        $this->assertSame(['Vacuna A' => 'applied'], $this->statuses());

        $this->actingAs($this->newFamily('Otra'));
        $this->delete("/vacunas/{$v->id}")->assertNotFound();
        $this->post("/vacunas/{$v->id}/restaurar")->assertNotFound();
        $this->post('/vacunas/esquema/dosis', ['vaccine_name' => 'X', 'age_months' => 1])->assertSessionHasNoErrors(); // su propio esquema
        $this->delete("/vacunas/esquema/dosis/{$dose->id}")->assertNotFound();
    }

    // ---- Recordatorios --------------------------------------------------------------------------

    public function test_every_pending_future_dose_gets_one_reminder_at_9am_local_with_three_notices(): void
    {
        $soon = $this->dose('A los 3 meses', 120, '1.ª dosis');   // 2027-01-01
        $this->dose('Al nacer', 0);                                // vencida: sin recordatorio
        $this->dose('Hoy mismo', 61);                              // hoy: sí (09:00 local aún no pasó)

        app(SyncVaccineReminders::class)->handle($this->mama->family->babies()->first());

        $rules = NotificationRule::where('event_type', NotificationEventType::Vaccine)->where('is_active', true)->get();
        $this->assertEqualsCanonicalizing(['Vacuna: A los 3 meses 1.ª dosis', 'Vacuna: Hoy mismo'], $rules->pluck('title')->all());

        $rule = $rules->firstWhere('subject_id', $soon->id);
        $this->assertTrue($rule->event_at->equalTo('2027-01-01 13:00:00')); // 09:00 local
        $this->assertSame([-10080, -1440, 0], $rule->offsets_minutes);
        $this->assertEqualsCanonicalizing([$this->mama->id, $this->papa->id], $rule->recipients()->pluck('caretakers.id')->all());
        $this->assertSame(3, $rule->occurrences()->count());
        $this->assertSame('2026-12-25 13:00:00', NotificationTarget::whereHas('occurrence', fn ($q) => $q->where('notification_rule_id', $rule->id))->min('next_send_at')); // 1 semana antes
    }

    public function test_sync_is_idempotent_and_follows_applications_deletions_and_undo(): void
    {
        $dose = $this->dose('A los 3 meses', 120);
        $baby = $this->mama->family->babies()->first();
        $sync = app(SyncVaccineReminders::class);

        $sync->handle($baby);
        $sync->handle($baby);
        $this->assertSame(1, NotificationRule::where('subject_type', VaccineDose::class)->count());
        $this->assertSame(6, NotificationTarget::count()); // 3 momentos × 2 cuidadores, sin duplicar

        // Aplicarla apaga el recordatorio…
        $this->post('/vacunas', ['vaccine_dose_id' => $dose->id, 'administered_at' => '2026-11-03T07:30']);
        $this->assertFalse(NotificationRule::first()->is_active);
        $this->assertSame(0, NotificationTarget::where('status', 'pending')->count());

        // …y deshacerlo lo vuelve a encender.
        $this->delete('/vacunas/'.Vaccination::first()->id);
        $this->assertTrue(NotificationRule::first()->is_active);
        $this->assertSame(6, NotificationTarget::where('status', 'pending')->count());

        // Borrar la dosis del esquema también lo apaga.
        $this->delete("/vacunas/esquema/dosis/{$dose->id}");
        $this->assertFalse(NotificationRule::first()->is_active);
        $this->assertSame(0, NotificationTarget::where('status', 'pending')->count());
    }

    public function test_changing_the_babys_age_reschedules_and_importing_creates_reminders(): void
    {
        $this->post('/vacunas/esquema/importar', ['text' => "Vacuna A; ; 4 meses\nVacuna B; ; 6 meses", 'replace' => true]);
        $this->assertSame(2, NotificationRule::where('is_active', true)->count());

        $baby = $this->mama->family->babies()->first();
        $baby->update(['birth_date' => '2026-09-10']); // una semana más joven
        app(SyncVaccineReminders::class)->handle($baby);

        $rule = NotificationRule::where('title', 'like', '%Vacuna A%')->first();
        $this->assertTrue($rule->event_at->equalTo(Carbon::parse('2026-09-10')->addDays(122)->format('Y-m-d').' 13:00:00')); // nacimiento + 122 días, 09:00 local
        $this->assertSame(3, $rule->occurrences()->where('status', 'active')->count());
    }

    // ---- Pantallas ----------------------------------------------------------------------------------

    public function test_form_page_lists_pending_doses_and_home_and_hub_show_the_next_vaccine(): void
    {
        $this->dose('Al nacer', 0);
        $next = $this->dose('A los 3 meses', 120, '1.ª dosis');
        $this->dose('A los 6 meses', 182);
        $this->post('/vacunas', ['vaccine_dose_id' => VaccineDose::where('vaccine_name', 'Al nacer')->first()->id, 'administered_at' => '2026-09-04T09:00']);

        $this->get("/vacunas/aplicar?dosis={$next->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Vacunas/Form')
            ->where('selected', $next->id)
            ->has('pending', 2)
            ->where('pending.0.name', 'A los 3 meses')
            ->where('clock.now', '2026-11-03T08:00'));

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('upcoming.0.kind', 'vaccine')
            ->where('upcoming.0.title', 'A los 3 meses 1.ª dosis')
            ->where('upcoming.0.overdue', false)
            ->where('upcoming.0.href', '/vacunas'));

        $this->get('/registrar')->assertInertia(fn (Assert $page) => $page->where('nextVaccine.name', 'A los 3 meses 1.ª dosis')->where('nextVaccine.due_on', '2027-01-01'));
    }

    public function test_an_overdue_dose_is_flagged_on_the_home(): void
    {
        $this->dose('Al nacer', 0);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('upcoming.0.overdue', true)->where('upcoming.0.title', 'Al nacer'));
    }

    public function test_everything_requires_login(): void
    {
        auth()->logout();

        foreach (['/vacunas', '/vacunas/aplicar', '/vacunas/esquema'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->post('/vacunas', [])->assertRedirect(route('login'));
        $this->post('/vacunas/esquema/importar', [])->assertRedirect(route('login'));
    }
}
