<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\CaretakerRole;
use App\Models\Caretaker;
use App\Models\Family;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    private function setupPayload(array $overrides = []): array
    {
        return $overrides + [
            'family_name' => 'Familia Martínez',
            'language' => 'es',
            'admin_name' => 'Ruben',
            'pin' => '482915',
            'pin_confirmation' => '482915',
            'baby_first_name' => 'Sofía',
            'baby_birth_date' => '2026-06-01',
            'baby_gender' => 'female',
        ];
    }

    private function createFamily(): Caretaker
    {
        return app(SetupFamily::class)->handle([
            'family_name' => 'Familia Martínez',
            'language' => 'es',
            'admin_name' => 'Ruben',
            'pin' => '482915',
            'baby_first_name' => 'Sofía',
            'baby_birth_date' => '2026-06-01',
        ]);
    }

    public function test_without_a_family_everything_redirects_to_the_wizard(): void
    {
        $this->get('/')->assertRedirect(route('setup.show'));
        $this->get('/entrar')->assertRedirect(route('setup.show'));

        $this->get('/configuracion')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Configuracion'));
    }

    public function test_wizard_creates_family_settings_admin_and_baby_and_signs_in(): void
    {
        $this->post('/configuracion', $this->setupPayload())->assertRedirect(route('inicio'));

        $family = Family::firstOrFail();
        $this->assertSame('familia-martinez', $family->slug);
        $this->assertSame('es', $family->settings->language);
        $this->assertSame('Sofía', $family->babies()->first()->first_name);

        $admin = $family->caretakers()->first();
        $this->assertSame(CaretakerRole::Admin, $admin->role);
        $this->assertNotSame('482915', $admin->getRawOriginal('pin'));
        $this->assertAuthenticatedAs($admin);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inicio')
            ->where('auth.caretaker.name', 'Ruben')
            ->where('family.slug', 'familia-martinez')
            ->has('babies', 1)
            ->has('t.setup'));
    }

    public function test_wizard_rejects_weak_or_mismatched_pins_and_future_birth_dates(): void
    {
        $this->post('/configuracion', $this->setupPayload(['pin' => '123456', 'pin_confirmation' => '123456']))
            ->assertSessionHasErrors('pin');
        $this->post('/configuracion', $this->setupPayload(['pin_confirmation' => '999999']))
            ->assertSessionHasErrors('pin');
        $this->post('/configuracion', $this->setupPayload(['baby_birth_date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('baby_birth_date');

        $this->assertDatabaseCount('families', 0);
    }

    public function test_wizard_is_unavailable_once_a_family_exists(): void
    {
        $this->createFamily();

        $this->get('/configuracion')->assertRedirect(route('login'));
        $this->post('/configuracion', $this->setupPayload())->assertRedirect(route('login'));
        $this->assertDatabaseCount('families', 1);
    }

    public function test_guests_are_sent_to_the_login_and_it_lists_active_caretakers(): void
    {
        $admin = $this->createFamily();
        $admin->family->caretakers()->create(['login_id' => 'abuela', 'name' => 'Abuela', 'pin' => '246802']);
        $admin->family->caretakers()->create(['login_id' => 'baja', 'name' => 'Baja', 'pin' => '246802', 'is_active' => false]);

        $this->get('/')->assertRedirect(route('login'));

        $this->get('/entrar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Entrar')
            ->where('needsFamily', false)
            ->has('caretakers', 2)
            ->missing('caretakers.0.pin'));
    }

    public function test_login_with_correct_pin_and_wrong_pin(): void
    {
        $admin = $this->createFamily();

        $this->post('/entrar', ['caretaker_id' => $admin->id, 'pin' => '000999'])
            ->assertSessionHasErrors(['pin' => trans('ui.auth.invalid')]);
        $this->assertGuest();

        $this->post('/entrar', ['caretaker_id' => $admin->id, 'pin' => '482915'])
            ->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_pin_is_locked_after_five_failed_attempts_even_with_the_right_pin(): void
    {
        $admin = $this->createFamily();

        foreach (range(1, 5) as $i) {
            $this->post('/entrar', ['caretaker_id' => $admin->id, 'pin' => '000999']);
        }

        $this->post('/entrar', ['caretaker_id' => $admin->id, 'pin' => '482915'])
            ->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_inactive_caretaker_cannot_sign_in(): void
    {
        $admin = $this->createFamily();
        $other = $admin->family->caretakers()->create([
            'login_id' => 'niñera', 'name' => 'Niñera', 'pin' => '246802', 'is_active' => false,
        ]);

        $this->post('/entrar', ['caretaker_id' => $other->id, 'pin' => '246802'])
            ->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_quick_switch_between_caretakers_and_logout(): void
    {
        $admin = $this->createFamily();
        $abuela = $admin->family->caretakers()->create(['login_id' => 'abuela', 'name' => 'Abuela', 'pin' => '246802']);

        $this->actingAs($admin)->post('/entrar', ['caretaker_id' => $abuela->id, 'pin' => '246802']);
        $this->assertAuthenticatedAs($abuela);

        $this->post('/salir')->assertRedirect(route('login', ['slug' => 'familia-martinez']));
        $this->assertGuest();
    }

    public function test_change_pin_requires_the_current_pin(): void
    {
        $admin = $this->createFamily();

        $this->actingAs($admin)
            ->put('/cuidador/pin', ['current_pin' => '000999', 'pin' => '135790', 'pin_confirmation' => '135790'])
            ->assertSessionHasErrors('current_pin');

        $this->put('/cuidador/pin', ['current_pin' => '482915', 'pin' => '135790', 'pin_confirmation' => '135790'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('135790', $admin->fresh()->getRawOriginal('pin')));
    }

    public function test_baby_selection_is_limited_to_the_own_family(): void
    {
        $admin = $this->createFamily();
        $second = $admin->family->babies()->create(['first_name' => 'Mateo', 'birth_date' => '2024-01-01']);
        $strangerBaby = Family::create(['slug' => 'otra', 'name' => 'Otra'])
            ->babies()->create(['first_name' => 'Ajeno', 'birth_date' => '2024-01-01']);

        $this->actingAs($admin)->post("/bebes/{$second->id}/seleccionar")->assertRedirect();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('activeBabyId', $second->id));

        $this->post("/bebes/{$strangerBaby->id}/seleccionar")->assertNotFound();
    }

    public function test_translations_follow_the_caretaker_language(): void
    {
        $admin = $this->createFamily();
        $admin->update(['language' => 'en']);

        $this->actingAs($admin)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'en')
            ->where('t.common.logout', 'Log out')
            ->where('t.app.name', 'KiDu baby tracker'));
    }
}
