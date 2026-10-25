<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\AppointmentStatus;
use App\Enums\MeasurementType;
use App\Enums\OccurrenceStatus;
use App\Enums\TargetStatus;
use App\Models\Appointment;
use App\Models\Caretaker;
use App\Models\Measurement;
use App\Models\NotificationRule;
use App\Models\NotificationTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CitasTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    private Caretaker $papa;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00'); // 08:00 en Santo Domingo (UTC-4)
        $this->mama = $this->newFamily('Ruiz');
        $this->papa = $this->mama->family->caretakers()->create(['login_id' => 'papa', 'name' => 'Papá', 'pin' => '246802']);
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

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'reason' => 'Control de los 5 meses',
            'doctor_name' => 'Dra. Pérez',
            'specialty' => 'Pediatría',
            'location' => 'Clínica Corazones Unidos',
            'scheduled_at' => '2026-11-05T09:30',
            'status' => 'scheduled',
            'symptoms' => null,
            'diagnosis' => null,
            'notes' => null,
            'follow_up_at' => null,
            'remind' => true,
            'offsets_minutes' => [-1440, -60],
            'vitals' => [],
        ];
    }

    private function create(array $overrides = []): Appointment
    {
        $this->post('/citas', $this->payload($overrides))->assertSessionHasNoErrors();

        return Appointment::latest('id')->firstOrFail();
    }

    // ---- Crear y editar -------------------------------------------------------

    public function test_creating_an_appointment_stores_utc_and_sets_up_its_reminder_for_every_caretaker(): void
    {
        $appointment = $this->create();

        $this->assertTrue($appointment->scheduled_at->equalTo('2026-11-05 13:30:00')); // 09:30 local
        $this->assertSame(AppointmentStatus::Scheduled, $appointment->status);
        $this->assertSame($this->mama->id, $appointment->created_by);

        $rule = $appointment->reminderRule;
        $this->assertSame('Cita: Control de los 5 meses', $rule->title);
        $this->assertSame('Dra. Pérez · Clínica Corazones Unidos', $rule->body);
        $this->assertSame([-1440, -60], $rule->offsets_minutes);
        $this->assertTrue($rule->subject->is($appointment));
        $this->assertEqualsCanonicalizing([$this->mama->id, $this->papa->id], $rule->recipients()->pluck('caretakers.id')->all());
        $this->assertSame(4, NotificationTarget::where('status', TargetStatus::Pending)->count()); // 2 momentos × 2 cuidadores
        $this->assertTrue(NotificationTarget::orderBy('next_send_at')->first()->next_send_at->equalTo('2026-11-04 13:30:00'));
    }

    public function test_past_cancelled_or_remind_off_appointments_do_not_notify(): void
    {
        $this->create(['scheduled_at' => '2026-11-01T09:00']);                       // ya pasó
        $this->create(['status' => 'cancelled']);
        $this->create(['remind' => false, 'offsets_minutes' => []]);

        $this->assertSame(0, NotificationTarget::where('status', TargetStatus::Pending)->count());
    }

    public function test_editing_reschedules_and_completing_or_cancelling_turns_the_reminder_off(): void
    {
        $appointment = $this->create();

        $this->put("/citas/{$appointment->id}", $this->payload(['scheduled_at' => '2026-11-06T10:00']))->assertSessionHasNoErrors();
        $pending = NotificationTarget::where('status', TargetStatus::Pending)->get();
        $this->assertCount(4, $pending);
        $this->assertTrue($pending->min('next_send_at')->equalTo('2026-11-05 14:00:00'));

        $this->put("/citas/{$appointment->id}", $this->payload(['scheduled_at' => '2026-11-06T10:00', 'status' => 'completed']));
        $this->assertSame(0, NotificationTarget::where('status', TargetStatus::Pending)->count());
        $this->assertFalse($appointment->fresh()->reminderRule->is_active);
        $this->assertNotNull($appointment->fresh()->completed_at);

        // Volver a programarla la reactiva.
        $this->put("/citas/{$appointment->id}", $this->payload(['scheduled_at' => '2026-11-07T10:00']));
        $this->assertTrue($appointment->fresh()->reminderRule->is_active);
        $this->assertNull($appointment->fresh()->completed_at);
        $this->assertSame(4, NotificationTarget::where('status', TargetStatus::Pending)->count());
        $this->assertSame(1, NotificationRule::count()); // siempre la misma regla
    }

    public function test_deleting_turns_the_reminder_off(): void
    {
        $appointment = $this->create();

        $this->delete("/citas/{$appointment->id}")->assertRedirect(route('appointments.index'));

        $this->assertSoftDeleted($appointment);
        $this->assertSame(0, NotificationTarget::where('status', TargetStatus::Pending)->count());
        $this->assertSame(OccurrenceStatus::Cancelled, $appointment->reminderRule->occurrences()->first()->status);
    }

    public function test_what_was_measured_at_the_visit_also_feeds_the_growth_measurements(): void
    {
        $this->mama->family->settings->update(['weight_unit' => 'lb']);
        $appointment = $this->create([
            'status' => 'completed',
            'scheduled_at' => '2026-11-03T07:00',
            'vitals' => ['weight' => 13.4, 'height' => 61.5, 'head_circumference' => null, 'temperature' => 36.8],
        ]);

        $weight = Measurement::where('type', MeasurementType::Weight)->firstOrFail();
        $this->assertSame($appointment->id, $weight->appointment_id);
        $this->assertSame('lb', $weight->unit);
        $this->assertSame('13.40', $weight->value);
        $this->assertSame('2026-11-03', $weight->measured_on->toDateString());
        $this->assertSame(3, Measurement::count());

        // Editar actualiza en vez de duplicar; vaciar un campo lo borra.
        $this->put("/citas/{$appointment->id}", $this->payload(['status' => 'completed', 'scheduled_at' => '2026-11-03T07:00', 'vitals' => ['weight' => 13.9, 'height' => null]]));
        $this->assertSame(1, Measurement::count());
        $this->assertSame('13.90', Measurement::first()->value);
    }

    public function test_validation(): void
    {
        $this->post('/citas', $this->payload(['reason' => '']))->assertSessionHasErrors('reason');
        $this->post('/citas', $this->payload(['scheduled_at' => 'mañana']))->assertSessionHasErrors('scheduled_at');
        $this->post('/citas', $this->payload(['status' => 'rara']))->assertSessionHasErrors('status');
        $this->post('/citas', $this->payload(['follow_up_at' => '2026-11-01T09:00']))->assertSessionHasErrors('follow_up_at');
        $this->post('/citas', $this->payload(['offsets_minutes' => [-7]]))->assertSessionHasErrors('offsets_minutes.0');
        $this->post('/citas', $this->payload(['remind' => true, 'offsets_minutes' => []]))->assertSessionHasErrors('offsets_minutes');
        $this->post('/citas', $this->payload(['vitals' => ['temperature' => 80]]))->assertSessionHasErrors('vitals.temperature');
        $this->assertDatabaseCount('appointments', 0);

        $this->post('/citas', $this->payload(['follow_up_at' => '2026-12-05T09:30']))->assertSessionHasNoErrors();
        $this->assertTrue(Appointment::first()->follow_up_at->equalTo('2026-12-05 13:30:00'));
    }

    // ---- Pantallas ----------------------------------------------------------------

    public function test_list_splits_upcoming_from_past_and_the_show_page_has_what_the_screen_needs(): void
    {
        $next = $this->create(['reason' => 'Vacunas', 'scheduled_at' => '2026-11-04T10:00']);
        $later = $this->create(['reason' => 'Control', 'scheduled_at' => '2026-12-01T10:00']);
        $past = $this->create(['reason' => 'Fiebre', 'scheduled_at' => '2026-10-20T10:00', 'status' => 'completed', 'diagnosis' => 'Resfriado']);

        $this->get('/citas')->assertInertia(fn (Assert $page) => $page
            ->component('Citas/Index')
            ->where('upcoming.0.reason', 'Vacunas')
            ->where('upcoming.1.reason', 'Control')
            ->where('past.0.reason', 'Fiebre')
            ->has('upcoming', 2)->has('past', 1));

        $this->get("/citas/{$past->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Citas/Show')
            ->where('appointment.diagnosis', 'Resfriado')
            ->where('appointment.status', 'completed')
            ->where('appointment.files', [])
            ->where('appointment.prescriptions', []));

        $this->get("/citas/{$next->id}/editar")->assertInertia(fn (Assert $page) => $page
            ->component('Citas/Form')
            ->where('appointment.scheduled_at', '2026-11-04T10:00')
            ->where('appointment.offsets_minutes', [-1440, -60])
            ->where('appointment.remind', true));

        $this->get('/citas/nueva')->assertInertia(fn (Assert $page) => $page
            ->where('defaults.scheduled_at', '2026-11-04T09:00')
            ->where('options.offsets', [0, -5, -10, -15, -30, -60, -120, -1440, -10080]));
    }

    public function test_the_home_shows_the_next_appointment(): void
    {
        $this->create(['reason' => 'Vacunas', 'scheduled_at' => '2026-11-04T10:00']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('upcoming.0.kind', 'appointment')
            ->where('upcoming.0.title', 'Vacunas')
            ->where('upcoming.0.href', '/citas/1'));

        $this->get('/registrar')->assertInertia(fn (Assert $page) => $page->where('nextAppointmentAt', fn ($v) => str_starts_with($v, '2026-11-04T14:00')));
    }

    public function test_everything_needs_login_and_stays_inside_the_family(): void
    {
        $appointment = $this->create();
        $stranger = $this->newFamily('Otra');

        $this->actingAs($stranger);
        $this->get("/citas/{$appointment->id}")->assertNotFound();
        $this->get("/citas/{$appointment->id}/editar")->assertNotFound();
        $this->put("/citas/{$appointment->id}", $this->payload())->assertNotFound();
        $this->delete("/citas/{$appointment->id}")->assertNotFound();
        $this->get('/citas')->assertInertia(fn (Assert $page) => $page->has('upcoming', 0));

        auth()->logout();
        $this->get('/citas')->assertRedirect(route('login'));
        $this->post('/citas', $this->payload())->assertRedirect(route('login'));
    }
}
