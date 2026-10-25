<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Models\BabyAllergen;
use App\Models\Caretaker;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactosAlergiasTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mama = $this->newFamily('Ruiz', 'Sofía');
        $this->actingAs($this->mama);
    }

    private function newFamily(string $name, string $baby): Caretaker
    {
        return app(SetupFamily::class)->handle([
            'family_name' => $name, 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => $baby, 'baby_birth_date' => '2026-06-01',
        ]);
    }

    // ---- Contactos ----------------------------------------------------------

    public function test_contact_is_created_and_listed_by_name(): void
    {
        $this->post('/contactos', ['name' => 'Dra. Pérez', 'role' => 'Pediatra', 'phone' => '+1 (809) 555-0100', 'email' => 'dra@example.com', 'address' => 'Calle 1', 'notes' => 'Lunes a viernes'])
            ->assertRedirect()->assertSessionHas('status');
        $this->post('/contactos', ['name' => 'Abuela']);

        $this->get('/contactos')->assertInertia(fn (Assert $page) => $page
            ->component('Contactos')->has('contacts', 2)->where('contacts.0.name', 'Abuela')->where('contacts.1.phone', '+1 (809) 555-0100'));
    }

    public function test_contact_validation(): void
    {
        $this->post('/contactos', ['name' => ''])->assertSessionHasErrors('name');
        $this->post('/contactos', ['name' => 'X', 'phone' => 'llámame'])->assertSessionHasErrors('phone');
        $this->post('/contactos', ['name' => 'X', 'email' => 'no-es-correo'])->assertSessionHasErrors('email');
        $this->assertSame(0, Contact::count());
    }

    public function test_contact_can_be_updated_deleted_and_restored(): void
    {
        $contact = Contact::create(['family_id' => $this->mama->family_id, 'name' => 'Dr. Gómez']);

        $this->put("/contactos/{$contact->id}", ['name' => 'Dr. Gómez R.', 'role' => 'Pediatra'])->assertRedirect();
        $this->assertSame('Pediatra', $contact->fresh()->role);

        $this->delete("/contactos/{$contact->id}")->assertSessionHas('undo.url');
        $this->assertSoftDeleted($contact);

        $this->post("/contactos/{$contact->id}/restaurar")->assertRedirect();
        $this->assertNotSoftDeleted($contact);
    }

    public function test_contacts_are_private_to_the_family(): void
    {
        $contact = Contact::create(['family_id' => $this->mama->family_id, 'name' => 'Secreto']);

        $this->actingAs($this->newFamily('Pérez', 'Luis'));

        $this->get('/contactos')->assertInertia(fn (Assert $page) => $page->has('contacts', 0));
        $this->put("/contactos/{$contact->id}", ['name' => 'Hackeado'])->assertNotFound();
        $this->delete("/contactos/{$contact->id}")->assertNotFound();
        $this->post("/contactos/{$contact->id}/restaurar")->assertNotFound();
        $this->assertSame('Secreto', $contact->fresh()->name);
    }

    // ---- Alergias -----------------------------------------------------------

    public function test_allergy_is_created_for_the_active_baby(): void
    {
        $this->post('/alergias', ['name' => 'Maní', 'type' => 'food', 'reaction_description' => 'Ronchas'])->assertRedirect();

        $allergy = BabyAllergen::firstOrFail();
        $this->assertSame($this->mama->family->babies()->first()->id, $allergy->baby_id);
        $this->assertSame($this->mama->id, $allergy->caretaker_id);

        $this->get('/alergias')->assertInertia(fn (Assert $page) => $page
            ->component('Alergias')->where('baby.first_name', 'Sofía')->has('allergies', 1)->where('allergies.0.type', 'food')->has('types', 4));
    }

    public function test_allergy_validation(): void
    {
        $this->post('/alergias', ['name' => '', 'type' => 'food'])->assertSessionHasErrors('name');
        $this->post('/alergias', ['name' => 'Polvo', 'type' => 'cosmic'])->assertSessionHasErrors('type');
    }

    public function test_allergy_update_delete_restore_and_isolation(): void
    {
        $baby = $this->mama->family->babies()->first();
        $allergy = BabyAllergen::create(['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'name' => 'Leche', 'type' => 'food']);

        $this->put("/alergias/{$allergy->id}", ['name' => 'Leche de vaca', 'type' => 'food'])->assertRedirect();
        $this->assertSame('Leche de vaca', $allergy->fresh()->name);

        $this->delete("/alergias/{$allergy->id}")->assertSessionHas('undo.url');
        $this->assertSoftDeleted($allergy);
        $this->post("/alergias/{$allergy->id}/restaurar")->assertRedirect();
        $this->assertNotSoftDeleted($allergy);

        $this->actingAs($this->newFamily('Pérez', 'Luis'));
        $this->put("/alergias/{$allergy->id}", ['name' => 'X', 'type' => 'food'])->assertNotFound();
        $this->delete("/alergias/{$allergy->id}")->assertNotFound();
    }

    public function test_forms_receive_only_the_relevant_allergies_of_this_baby(): void
    {
        $baby = $this->mama->family->babies()->first();
        foreach ([['Maní', 'food'], ['Penicilina', 'medicine'], ['Polen', 'environment']] as [$name, $type]) {
            BabyAllergen::create(['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'name' => $name, 'type' => $type]);
        }

        $this->get('/registrar/solidos')->assertInertia(fn (Assert $page) => $page->where('allergies', ['Maní']));
        $this->get('/registrar/medicina')->assertInertia(fn (Assert $page) => $page->where('allergies', ['Penicilina']));
        $this->get('/registrar/panal')->assertInertia(fn (Assert $page) => $page->where('allergies', []));
    }

    public function test_login_is_required(): void
    {
        auth()->logout();

        foreach (['/contactos', '/alergias'] as $url) {
            $this->get($url)->assertRedirect();
        }
        $this->post('/contactos', ['name' => 'X'])->assertRedirect();
    }
}
