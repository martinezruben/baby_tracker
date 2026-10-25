<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Actions\SyncVaccineReminders;
use App\Enums\CaretakerRole;
use App\Enums\NotificationEventType;
use App\Enums\TargetStatus;
use App\Models\Appointment;
use App\Models\Baby;
use App\Models\Caretaker;
use App\Models\NotificationRule;
use App\Models\NotificationTarget;
use App\Models\PushSubscription;
use App\Models\VaccineDose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AjustesTest extends TestCase
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
            'baby_first_name' => 'Sofía', 'baby_birth_date' => '2026-09-03',
        ]);
        $caretaker->family->settings->update(['timezone' => 'America/Santo_Domingo']);

        return $caretaker;
    }

    private function caretakerPayload(array $o = []): array
    {
        return $o + ['name' => 'Abuela Rosa', 'type' => 'Abuela', 'role' => 'user', 'badge_color' => '#A63A86', 'language' => null, 'pin' => '246802', 'pin_confirmation' => '246802'];
    }

    // ---- Solo administradores ------------------------------------------------------------------

    public function test_only_admins_reach_the_family_settings(): void
    {
        $helper = $this->mama->family->caretakers()->create(['login_id' => 'ab1', 'name' => 'Abuela', 'pin' => '246802', 'role' => CaretakerRole::User]);

        $urls = ['/ajustes', '/ajustes/preferencias', '/ajustes/cuidadores', '/ajustes/bebes'];
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($helper);
        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/ajustes/cuidadores', $this->caretakerPayload())->assertForbidden();
        $this->put('/ajustes/preferencias', [])->assertForbidden();
        $this->post('/ajustes/bebes', [])->assertForbidden();
        $this->post("/ajustes/cuidadores/{$this->mama->id}/baja")->assertForbidden();

        auth()->logout();
        $this->get('/ajustes')->assertRedirect(route('login'));
    }

    // ---- Cuidadores -----------------------------------------------------------------------------

    public function test_adding_a_caretaker_stores_a_hashed_pin_and_a_unique_login_id(): void
    {
        $this->post('/ajustes/cuidadores', $this->caretakerPayload())->assertSessionHasNoErrors();
        $this->post('/ajustes/cuidadores', $this->caretakerPayload(['pin' => '135790', 'pin_confirmation' => '135790']));

        $rosa = Caretaker::where('name', 'Abuela Rosa')->orderBy('id')->firstOrFail();
        $this->assertSame(CaretakerRole::User, $rosa->role);
        $this->assertNotSame('246802', $rosa->getRawOriginal('pin'));
        $this->assertTrue(password_verify('246802', $rosa->getRawOriginal('pin')));
        $this->assertTrue($rosa->is_active);
        $this->assertSame(2, Caretaker::where('name', 'Abuela Rosa')->pluck('login_id')->unique()->count());

        // Y puede entrar con su PIN.
        auth()->logout();
        $this->post('/entrar', ['caretaker_id' => $rosa->id, 'pin' => '246802'])->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($rosa);
    }

    public function test_caretaker_validation(): void
    {
        $this->post('/ajustes/cuidadores', $this->caretakerPayload(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/ajustes/cuidadores', $this->caretakerPayload(['pin' => '123456', 'pin_confirmation' => '123456']))->assertSessionHasErrors('pin');
        $this->post('/ajustes/cuidadores', $this->caretakerPayload(['pin_confirmation' => '999999']))->assertSessionHasErrors('pin');
        $this->post('/ajustes/cuidadores', $this->caretakerPayload(['pin' => null, 'pin_confirmation' => null]))->assertSessionHasErrors('pin');
        $this->post('/ajustes/cuidadores', $this->caretakerPayload(['role' => 'rey']))->assertSessionHasErrors('role');
        $this->post('/ajustes/cuidadores', $this->caretakerPayload(['badge_color' => '#123456']))->assertSessionHasErrors('badge_color');
        $this->post('/ajustes/cuidadores', $this->caretakerPayload(['language' => 'fr']))->assertSessionHasErrors('language');
        $this->assertSame(1, Caretaker::count());
    }

    public function test_editing_can_reset_the_pin_but_keeps_it_when_left_empty(): void
    {
        $this->post('/ajustes/cuidadores', $this->caretakerPayload());
        $rosa = Caretaker::where('name', 'Abuela Rosa')->firstOrFail();
        $before = $rosa->getRawOriginal('pin');

        $this->put("/ajustes/cuidadores/{$rosa->id}", $this->caretakerPayload(['name' => 'Rosa', 'pin' => null, 'pin_confirmation' => null]))->assertSessionHasNoErrors();
        $this->assertSame('Rosa', $rosa->fresh()->name);
        $this->assertSame($before, $rosa->fresh()->getRawOriginal('pin'));

        $this->put("/ajustes/cuidadores/{$rosa->id}", $this->caretakerPayload(['pin' => '864209', 'pin_confirmation' => '864209']));
        $this->assertTrue(password_verify('864209', $rosa->fresh()->getRawOriginal('pin')));
        $this->put("/ajustes/cuidadores/{$rosa->id}", $this->caretakerPayload(['pin' => '111111', 'pin_confirmation' => '111111']))->assertSessionHasErrors('pin');
    }

    public function test_the_last_admin_can_neither_be_demoted_nor_deactivated_and_nobody_deactivates_themselves(): void
    {
        $this->put("/ajustes/cuidadores/{$this->mama->id}", $this->caretakerPayload(['name' => 'Mamá', 'role' => 'user', 'pin' => null, 'pin_confirmation' => null]))
            ->assertSessionHasErrors('role');
        $this->assertSame(CaretakerRole::Admin, $this->mama->fresh()->role);

        $this->post("/ajustes/cuidadores/{$this->mama->id}/baja")->assertSessionHasErrors('caretaker');
        $this->assertTrue($this->mama->fresh()->is_active);

        // Con un segundo administrador sí se puede degradar al primero.
        $this->post('/ajustes/cuidadores', $this->caretakerPayload(['name' => 'Papá', 'role' => 'admin']));
        $this->put("/ajustes/cuidadores/{$this->mama->id}", $this->caretakerPayload(['name' => 'Mamá', 'role' => 'user', 'pin' => null, 'pin_confirmation' => null]))->assertSessionHasNoErrors();

        // …y entonces el último admin (Papá) vuelve a estar protegido.
        $papa = Caretaker::where('name', 'Papá')->firstOrFail();
        $this->actingAs($papa);
        $this->put("/ajustes/cuidadores/{$papa->id}", $this->caretakerPayload(['name' => 'Papá', 'role' => 'user', 'pin' => null, 'pin_confirmation' => null]))->assertSessionHasErrors('role');
    }

    public function test_deactivating_stops_alerts_devices_and_sign_in_but_keeps_the_records(): void
    {
        $this->post('/ajustes/cuidadores', $this->caretakerPayload());
        $rosa = Caretaker::where('name', 'Abuela Rosa')->firstOrFail();
        PushSubscription::create(['caretaker_id' => $rosa->id, 'endpoint' => 'https://push.example/rosa', 'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example/rosa'), 'p256dh' => 'k', 'auth' => 'a']);

        $baby = $this->mama->family->babies()->first();
        $this->post('/citas', [
            'reason' => 'Control', 'scheduled_at' => '2026-11-05T09:30', 'status' => 'scheduled', 'remind' => true, 'offsets_minutes' => [-1440, -60], 'vitals' => [],
        ]);
        $this->assertSame(2, NotificationTarget::where('caretaker_id', $rosa->id)->where('status', TargetStatus::Pending)->count());

        $this->post("/ajustes/cuidadores/{$rosa->id}/baja")->assertSessionHasNoErrors();

        $this->assertFalse($rosa->fresh()->is_active);
        $this->assertSame(0, $rosa->pushSubscriptions()->count());
        $this->assertSame(0, NotificationTarget::where('caretaker_id', $rosa->id)->where('status', TargetStatus::Pending)->count());
        $this->assertSame(0, $rosa->recipientRules()->count());
        $this->assertSoftDeleteFree($rosa);
        // Los avisos de los demás siguen igual.
        $this->assertSame(2, NotificationTarget::where('caretaker_id', $this->mama->id)->where('status', TargetStatus::Pending)->count());

        auth()->logout();
        $this->post('/entrar', ['caretaker_id' => $rosa->id, 'pin' => '246802'])->assertSessionHasErrors('pin');
        $this->assertGuest();
        $this->assertNotNull($baby);
    }

    private function assertSoftDeleteFree(Caretaker $c): void
    {
        $this->assertNull(Caretaker::find($c->id)->deleted_at); // no se borra: se da de baja
    }

    public function test_a_new_or_reactivated_caretaker_gets_the_alerts_of_what_is_already_scheduled(): void
    {
        $this->post('/citas', ['reason' => 'Control', 'scheduled_at' => '2026-11-05T09:30', 'status' => 'scheduled', 'remind' => true, 'offsets_minutes' => [-1440, -60], 'vitals' => []]);
        $appointment = Appointment::firstOrFail();
        // Un recordatorio suelto elige sus destinatarios: no se toca.
        NotificationRule::create(['family_id' => $this->mama->family_id, 'event_type' => NotificationEventType::Custom, 'title' => 'Suelto', 'event_at' => '2026-11-06 14:00:00', 'offsets_minutes' => [0], 'snooze_options' => [10], 'channels' => ['push'], 'is_active' => true]);

        $this->post('/ajustes/cuidadores', $this->caretakerPayload());
        $rosa = Caretaker::where('name', 'Abuela Rosa')->firstOrFail();

        $this->assertTrue($appointment->reminderRule->recipients()->where('caretakers.id', $rosa->id)->exists());
        $this->assertSame(2, NotificationTarget::where('caretaker_id', $rosa->id)->count()); // 1 día antes y 1 hora antes
        $this->assertSame(0, NotificationRule::where('event_type', NotificationEventType::Custom)->first()->recipients()->count());

        // Los avisos que ya pasaron no se le crean: la cita es el 5 a las 09:30 local (13:30 UTC); "1 día antes" ya pasó si estamos el 4.
        $this->post("/ajustes/cuidadores/{$rosa->id}/baja");
        Carbon::setTestNow('2026-11-04 15:00:00');
        $this->post("/ajustes/cuidadores/{$rosa->id}/alta")->assertSessionHasNoErrors();
        $this->assertTrue($rosa->fresh()->is_active);
        $this->assertSame(1, NotificationTarget::where('caretaker_id', $rosa->id)->where('status', TargetStatus::Pending)->count());
    }

    public function test_a_family_cannot_manage_another_familys_caretakers(): void
    {
        $this->post('/ajustes/cuidadores', $this->caretakerPayload());
        $rosa = Caretaker::where('name', 'Abuela Rosa')->firstOrFail();

        $this->actingAs($this->newFamily('Otra'));
        $this->put("/ajustes/cuidadores/{$rosa->id}", $this->caretakerPayload())->assertNotFound();
        $this->post("/ajustes/cuidadores/{$rosa->id}/baja")->assertNotFound();
        $this->post("/ajustes/cuidadores/{$rosa->id}/alta")->assertNotFound();
        $this->get('/ajustes/cuidadores')->assertInertia(fn (Assert $page) => $page->has('caretakers', 1));
    }

    // ---- Bebés ---------------------------------------------------------------------------------------

    public function test_adding_and_editing_a_baby_resyncs_the_vaccine_reminders(): void
    {
        VaccineDose::create(['family_id' => $this->mama->family_id, 'vaccine_name' => 'Vacuna A', 'age_days' => 120]);

        $this->post('/ajustes/bebes', ['first_name' => 'Mateo', 'birth_date' => '2026-10-20', 'gender' => 'male', 'feed_warning_minutes' => 180, 'diaper_warning_minutes' => 180])
            ->assertSessionHasNoErrors();
        $mateo = Baby::where('first_name', 'Mateo')->firstOrFail();

        $this->assertSame(1, NotificationRule::where('baby_id', $mateo->id)->where('is_active', true)->count());
        $rule = NotificationRule::where('baby_id', $mateo->id)->first();
        $this->assertTrue($rule->event_at->equalTo(Carbon::parse('2026-10-20')->addDays(120)->format('Y-m-d').' 13:00:00'));

        $this->put("/ajustes/bebes/{$mateo->id}", ['first_name' => 'Mateo', 'birth_date' => '2026-10-27', 'feed_warning_minutes' => 120, 'diaper_warning_minutes' => 120]);
        $this->assertTrue($rule->fresh()->event_at->equalTo(Carbon::parse('2026-10-27')->addDays(120)->format('Y-m-d').' 13:00:00'));
        $this->assertSame(120, $mateo->fresh()->feed_warning_minutes);
    }

    public function test_baby_validation(): void
    {
        $ok = ['first_name' => 'Mateo', 'birth_date' => '2026-10-20', 'feed_warning_minutes' => 180, 'diaper_warning_minutes' => 180];

        $this->post('/ajustes/bebes', ['first_name' => ''] + $ok)->assertSessionHasErrors('first_name');
        $this->post('/ajustes/bebes', ['birth_date' => '2026-12-01'] + $ok)->assertSessionHasErrors('birth_date');
        $this->post('/ajustes/bebes', ['birth_date' => 'ayer'] + $ok)->assertSessionHasErrors('birth_date');
        $this->post('/ajustes/bebes', ['feed_warning_minutes' => 5] + $ok)->assertSessionHasErrors('feed_warning_minutes');
        $this->post('/ajustes/bebes', ['gender' => 'otro'] + $ok)->assertSessionHasErrors('gender');
        $this->assertSame(1, Baby::count());
    }

    public function test_removing_a_baby_keeps_the_last_one_and_turns_its_reminders_off(): void
    {
        $this->post('/ajustes/bebes', ['first_name' => 'Mateo', 'birth_date' => '2026-10-20', 'feed_warning_minutes' => 180, 'diaper_warning_minutes' => 180]);
        $mateo = Baby::where('first_name', 'Mateo')->firstOrFail();
        VaccineDose::create(['family_id' => $this->mama->family_id, 'vaccine_name' => 'Vacuna A', 'age_days' => 120]);
        app(SyncVaccineReminders::class)->handle($mateo);

        $this->delete("/ajustes/bebes/{$mateo->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted($mateo);
        $this->assertSame(0, NotificationRule::where('baby_id', $mateo->id)->where('is_active', true)->count());
        $this->assertSame(0, NotificationTarget::whereHas('occurrence.rule', fn ($q) => $q->where('baby_id', $mateo->id))->where('status', TargetStatus::Pending)->count());

        $sofia = Baby::where('first_name', 'Sofía')->firstOrFail();
        $this->delete("/ajustes/bebes/{$sofia->id}")->assertSessionHasErrors('baby'); // debe quedar al menos uno
        $this->assertNotSoftDeleted($sofia);
    }

    public function test_the_selected_baby_falls_back_when_it_is_removed(): void
    {
        $this->post('/ajustes/bebes', ['first_name' => 'Mateo', 'birth_date' => '2026-10-20', 'feed_warning_minutes' => 180, 'diaper_warning_minutes' => 180]);
        $mateo = Baby::where('first_name', 'Mateo')->firstOrFail();
        $this->post("/bebes/{$mateo->id}/seleccionar");

        $this->delete("/ajustes/bebes/{$mateo->id}");

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('baby.first_name', 'Sofía'));
    }

    public function test_a_family_cannot_touch_another_familys_babies(): void
    {
        $sofia = Baby::firstOrFail();
        $this->actingAs($this->newFamily('Otra'));

        $this->put("/ajustes/bebes/{$sofia->id}", ['first_name' => 'X', 'birth_date' => '2026-01-01', 'feed_warning_minutes' => 180, 'diaper_warning_minutes' => 180])->assertNotFound();
        $this->delete("/ajustes/bebes/{$sofia->id}")->assertNotFound();
        $this->assertSame('Sofía', $sofia->fresh()->first_name);
    }

    // ---- Preferencias -----------------------------------------------------------------------------------

    public function test_preferences_update_family_name_units_and_formats(): void
    {
        $this->put('/ajustes/preferencias', [
            'family_name' => 'Los Ruiz', 'language' => 'en', 'timezone' => 'America/New_York', 'time_format' => '24',
            'volume_unit' => 'oz', 'weight_unit' => 'lb', 'height_unit' => 'in', 'temperature_unit' => 'F', 'growth_chart_standard' => 'cdc',
        ])->assertSessionHasNoErrors();

        $s = $this->mama->family->settings->fresh();
        $this->assertSame('Los Ruiz', $this->mama->family->fresh()->name);
        $this->assertSame(['en', 'America/New_York', '24', 'oz', 'lb', 'in', 'F', 'cdc'], [$s->language, $s->timezone, $s->time_format, $s->volume_unit, $s->weight_unit, $s->height_unit, $s->temperature_unit, $s->growth_chart_standard]);

        $this->get('/ajustes/preferencias')->assertInertia(fn (Assert $page) => $page->component('Ajustes/Preferencias')->where('prefs.timezone', 'America/New_York')->has('timezones'));
    }

    public function test_preferences_validation(): void
    {
        $ok = ['family_name' => 'Ruiz', 'language' => 'es', 'timezone' => 'America/Santo_Domingo', 'time_format' => '12', 'volume_unit' => 'ml', 'weight_unit' => 'kg', 'height_unit' => 'cm', 'temperature_unit' => 'C', 'growth_chart_standard' => 'who'];

        $this->put('/ajustes/preferencias', ['timezone' => 'Marte/Olimpo'] + $ok)->assertSessionHasErrors('timezone');
        $this->put('/ajustes/preferencias', ['language' => 'klingon'] + $ok)->assertSessionHasErrors('language');
        $this->put('/ajustes/preferencias', ['time_format' => '36'] + $ok)->assertSessionHasErrors('time_format');
        $this->put('/ajustes/preferencias', ['volume_unit' => 'galones'] + $ok)->assertSessionHasErrors('volume_unit');
        $this->put('/ajustes/preferencias', ['family_name' => ''] + $ok)->assertSessionHasErrors('family_name');
        $this->put('/ajustes/preferencias', $ok)->assertSessionHasNoErrors();
    }

    public function test_changing_the_time_zone_reschedules_the_vaccine_reminders(): void
    {
        VaccineDose::create(['family_id' => $this->mama->family_id, 'vaccine_name' => 'Vacuna A', 'age_days' => 120]);
        app(SyncVaccineReminders::class)->handle(Baby::firstOrFail());
        $rule = NotificationRule::firstOrFail();
        $this->assertTrue($rule->event_at->equalTo('2027-01-01 13:00:00')); // 09:00 en Santo Domingo (UTC-4)

        $this->put('/ajustes/preferencias', [
            'family_name' => 'Ruiz', 'language' => 'es', 'timezone' => 'Europe/Madrid', 'time_format' => '24', 'volume_unit' => 'ml',
            'weight_unit' => 'kg', 'height_unit' => 'cm', 'temperature_unit' => 'C', 'growth_chart_standard' => 'who',
        ]);

        $this->assertTrue($rule->fresh()->event_at->equalTo('2027-01-01 08:00:00')); // 09:00 en Madrid (UTC+1)
    }

    public function test_every_caretaker_can_pick_their_own_language(): void
    {
        $this->patch('/cuidador/idioma', ['language' => 'en'])->assertSessionHasNoErrors();
        $this->assertSame('en', $this->mama->fresh()->language);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));

        $this->patch('/cuidador/idioma', ['language' => null]);
        $this->assertNull($this->mama->fresh()->language);
        $this->patch('/cuidador/idioma', ['language' => 'fr'])->assertSessionHasErrors('language');
    }
}
