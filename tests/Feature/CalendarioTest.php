<?php

namespace Tests\Feature;

use App\Actions\SavePrescription;
use App\Actions\SetupFamily;
use App\Enums\DiaperType;
use App\Enums\FeedType;
use App\Enums\LifeEventType;
use App\Enums\NotificationEventType;
use App\Models\Appointment;
use App\Models\Caretaker;
use App\Models\DiaperLog;
use App\Models\FeedLog;
use App\Models\LifeEvent;
use App\Models\NotificationRule;
use App\Models\Vaccination;
use App\Models\VaccineDose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00'); // 08:00 en Santo Domingo (UTC-4)
        $this->mama = $this->newFamily('Ruiz');
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

    private function owner(?Caretaker $who = null): array
    {
        $who ??= $this->mama;

        return ['family_id' => $who->family_id, 'baby_id' => $who->family->babies()->first()->id];
    }

    private function days(string $query = ''): array
    {
        return $this->get('/calendario'.$query)->viewData('page')['props']['data']['days'];
    }

    public function test_items_land_on_the_family_day_not_the_utc_day(): void
    {
        // 2026-11-04 03:30 UTC = 3 de noviembre, 23:30 en la familia.
        Appointment::create($this->owner() + ['reason' => 'Control', 'scheduled_at' => '2026-11-04 03:30:00']);
        // 2026-11-04 04:00 UTC = 4 de noviembre 00:00 local.
        Appointment::create($this->owner() + ['reason' => 'Vacunas', 'scheduled_at' => '2026-11-04 04:00:00']);

        $days = $this->days('?mes=2026-11');

        $this->assertSame(['Control'], array_column($days['2026-11-03']['items'], 'title'));
        $this->assertSame(['Vacunas'], array_column($days['2026-11-04']['items'], 'title'));
        $this->assertSame('/citas/1', $days['2026-11-03']['items'][0]['href']);
    }

    public function test_cancelled_appointments_other_months_babies_and_families_are_left_out(): void
    {
        Appointment::create($this->owner() + ['reason' => 'Cancelada', 'scheduled_at' => '2026-11-12 14:00:00', 'status' => 'cancelled']);
        Appointment::create($this->owner() + ['reason' => 'De octubre', 'scheduled_at' => '2026-10-31 14:00:00']);
        Appointment::create($this->owner() + ['reason' => 'De diciembre', 'scheduled_at' => '2026-12-01 14:00:00']);
        $other = $this->mama->family->babies()->create(['first_name' => 'Mateo', 'birth_date' => '2026-10-20']);
        Appointment::create(['family_id' => $this->mama->family_id, 'baby_id' => $other->id, 'reason' => 'De Mateo', 'scheduled_at' => '2026-11-12 14:00:00']);
        $stranger = $this->newFamily('Otra');
        Appointment::create($this->owner($stranger) + ['reason' => 'Ajena', 'scheduled_at' => '2026-11-12 14:00:00']);

        $this->assertSame([], $this->days('?mes=2026-11'));
    }

    public function test_every_kind_of_event_shows_up_with_its_state(): void
    {
        // Vacunas: una dosis sin aplicar con fecha en noviembre (vencida o pendiente) y una aplicada.
        $due = VaccineDose::create(['family_id' => $this->mama->family_id, 'vaccine_name' => 'Vacuna A', 'dose_label' => '1.ª', 'age_days' => 70]); // 2026-11-12
        $given = VaccineDose::create(['family_id' => $this->mama->family_id, 'vaccine_name' => 'Vacuna B', 'age_days' => 0]);
        $baby = $this->mama->family->babies()->first();
        Vaccination::create(['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'vaccine_dose_id' => $given->id, 'vaccine_name' => 'Vacuna B', 'administered_at' => '2026-11-05 15:00:00']);

        // Tomas de una medicina (cada 12 h desde el día 8).
        app(SavePrescription::class)->handle($this->mama, $baby, null, [
            'new_medicine' => 'Amoxicilina', 'dose' => 5, 'unit' => 'ml', 'interval_hours' => 12, 'total_doses' => 2, 'starts_at' => Carbon::parse('2026-11-14 12:00:00'),
        ]);

        NotificationRule::create([
            'family_id' => $baby->family_id, 'event_type' => NotificationEventType::Custom, 'title' => 'Llamar a la abuela', 'event_at' => '2026-11-20 22:00:00',
            'offsets_minutes' => [0], 'snooze_options' => [10], 'channels' => ['push'], 'is_active' => true,
        ]);
        LifeEvent::create($this->owner() + ['type' => LifeEventType::Tooth, 'occurred_on' => '2026-11-07', 'detail' => 'Incisivo']);

        $days = $this->days('?mes=2026-11');

        $this->assertSame('pending', $days['2026-11-12']['items'][0]['state']);
        $this->assertSame('vaccine', $days['2026-11-12']['items'][0]['kind']);
        $this->assertSame('done', $days['2026-11-05']['items'][0]['state']); // aplicada
        $this->assertSame(['dose', 'dose'], array_column($days['2026-11-14']['items'], 'kind'));
        $this->assertSame('reminder', $days['2026-11-20']['items'][0]['kind']); // 22:00 UTC = 18:00 local, mismo día
        $this->assertSame('life_event', $days['2026-11-07']['items'][0]['kind']);
        $this->assertSame('/eventos/1', $days['2026-11-07']['items'][0]['href']);
    }

    public function test_overdue_vaccines_are_flagged(): void
    {
        VaccineDose::create(['family_id' => $this->mama->family_id, 'vaccine_name' => 'Vacuna A', 'age_days' => 60]); // 2026-11-02, ya pasó

        $this->assertSame('overdue', $this->days('?mes=2026-11')['2026-11-02']['items'][0]['state']);
    }

    public function test_activity_is_counted_per_day_and_type(): void
    {
        $owner = $this->owner();
        foreach (['2026-11-03 10:00:00', '2026-11-03 14:00:00', '2026-11-04 03:00:00'] as $at) { // el último es el 3 por la noche (local)
            FeedLog::create($owner + ['occurred_at' => $at, 'type' => FeedType::Bottle, 'amount' => 90, 'unit' => 'ml']);
        }
        DiaperLog::create($owner + ['occurred_at' => '2026-11-03 11:00:00', 'type' => DiaperType::Wet]);
        DiaperLog::create($owner + ['occurred_at' => '2026-11-03 12:00:00', 'type' => DiaperType::Wet])->delete();

        $day = $this->days('?mes=2026-11')['2026-11-03'];

        $this->assertSame(['feed' => 3, 'diaper' => 1], $day['counts']);
        $this->assertSame([], $day['items']);
    }

    public function test_default_and_invalid_months_fall_back_to_the_current_one(): void
    {
        DiaperLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:00:00', 'type' => DiaperType::Wet]);

        foreach (['', '?mes=nada', '?mes=2026-13', '?mes=2026-1'] as $query) {
            $this->get('/calendario'.$query)->assertInertia(fn (Assert $page) => $page
                ->component('Calendario')
                ->where('data.month', '2026-11')
                ->where('today', '2026-11-10')
                ->has('data.days.2026-11-03'));
        }

        $this->get('/calendario?mes=2026-10')->assertInertia(fn (Assert $page) => $page->where('data.month', '2026-10')->where('data.days', []));
    }

    public function test_the_log_can_be_limited_to_one_day_in_the_family_timezone(): void
    {
        $owner = $this->owner();
        DiaperLog::create($owner + ['occurred_at' => '2026-11-04 03:59:00', 'type' => DiaperType::Wet]); // 3 nov 23:59 local
        DiaperLog::create($owner + ['occurred_at' => '2026-11-04 04:00:00', 'type' => DiaperType::Wet]); // 4 nov 00:00 local
        DiaperLog::create($owner + ['occurred_at' => '2026-11-04 20:00:00', 'type' => DiaperType::Wet]); // 4 nov 16:00 local
        DiaperLog::create($owner + ['occurred_at' => '2026-11-05 04:00:00', 'type' => DiaperType::Wet]); // 5 nov 00:00 local

        $count = fn (string $q) => count($this->get('/bitacora'.$q)->viewData('page')['props']['entries']);

        $this->assertSame(4, $count(''));
        $this->assertSame(2, $count('?dia=2026-11-04'));
        $this->assertSame(1, $count('?dia=2026-11-03'));
        $this->assertSame(4, $count('?dia=basura'));
        $this->assertSame(4, $count('?dia=2026-02-31x'));
        $this->get('/bitacora?dia=2026-11-04')->assertInertia(fn (Assert $page) => $page->where('dia', '2026-11-04'));
    }

    public function test_requires_login(): void
    {
        auth()->logout();
        $this->get('/calendario')->assertRedirect(route('login'));
    }
}
