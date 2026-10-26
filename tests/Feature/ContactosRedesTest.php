<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Models\Caretaker;
use App\Models\Contact;
use App\Support\Backup\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactosRedesTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->mama = $this->newFamily('Ruiz');
        $this->actingAs($this->mama);
    }

    private function newFamily(string $name): Caretaker
    {
        return app(SetupFamily::class)->handle([
            'family_name' => $name, 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => 'Sofía', 'baby_birth_date' => '2026-06-01',
        ]);
    }

    public function test_personal_and_work_phones_are_kept_apart(): void
    {
        $this->post('/contactos', ['name' => 'Dra. Pérez', 'phone' => '809 555 0100', 'work_phone' => '+1 829 555 0200'])->assertSessionHasNoErrors();

        $contact = Contact::firstOrFail();
        $this->assertSame('809 555 0100', $contact->phone);
        $this->assertSame('+1 829 555 0200', $contact->work_phone);
        $this->get('/contactos')->assertInertia(fn (Assert $page) => $page->where('contacts.0.phone', '809 555 0100')->where('contacts.0.work_phone', '+1 829 555 0200'));

        $this->post('/contactos', ['name' => 'X', 'work_phone' => 'llámame'])->assertSessionHasErrors('work_phone');
    }

    public function test_instagram_accepts_a_handle_or_a_profile_link_and_stores_only_the_user(): void
    {
        foreach (['@kidu.baby' => 'kidu.baby', 'kidu_baby' => 'kidu_baby', 'https://www.instagram.com/doctora.perez/?igsh=abc' => 'doctora.perez', 'instagram.com/pedi_atra/' => 'pedi_atra'] as $input => $expected) {
            Contact::query()->forceDelete();
            $this->post('/contactos', ['name' => 'C', 'instagram' => $input])->assertSessionHasNoErrors();
            $this->assertSame($expected, Contact::firstOrFail()->instagram, $input);
        }

        $this->post('/contactos', ['name' => 'C', 'instagram' => 'no valido!'])->assertSessionHasErrors('instagram');
        $this->post('/contactos', ['name' => 'C', 'instagram' => str_repeat('a', 31)])->assertSessionHasErrors('instagram');
    }

    public function test_photo_is_stored_as_a_private_square_jpeg_and_served_only_to_the_family(): void
    {
        $this->post('/contactos', ['name' => 'Dra. Pérez', 'photo' => UploadedFile::fake()->image('yo.png', 900, 600)])->assertSessionHasNoErrors();

        $contact = Contact::firstOrFail();
        $this->assertStringStartsWith("contacts/{$contact->family_id}/", $contact->photo_path);
        $this->assertStringEndsWith('.jpg', $contact->photo_path);
        $bytes = Storage::disk('local')->get($contact->photo_path);
        [$w, $h, $type] = getimagesizefromstring($bytes);
        $this->assertSame([480, 480, IMAGETYPE_JPEG], [$w, $h, $type]);

        $this->get('/contactos')->assertInertia(fn (Assert $page) => $page->where('contacts.0.photo_url', fn ($v) => str_contains($v, "/contactos/{$contact->id}/foto?v=")));
        $this->get("/contactos/{$contact->id}/foto")->assertOk()->assertHeader('content-type', 'image/jpeg')->assertHeader('x-content-type-options', 'nosniff');

        $this->actingAs($this->newFamily('Pérez'));
        $this->get("/contactos/{$contact->id}/foto")->assertNotFound();

        auth()->logout();
        $this->get("/contactos/{$contact->id}/foto")->assertRedirect();
    }

    public function test_photo_can_be_replaced_and_removed_through_a_multipart_update(): void
    {
        $this->post('/contactos', ['name' => 'Dra. Pérez', 'photo' => UploadedFile::fake()->image('a.jpg', 600, 600)]);
        $contact = Contact::firstOrFail();
        $first = $contact->photo_path;

        // Como lo envía la app: POST multipart con el método PUT simulado.
        $this->post("/contactos/{$contact->id}", ['_method' => 'put', 'name' => 'Dra. Pérez', 'photo' => UploadedFile::fake()->image('b.jpg', 700, 500)])->assertSessionHasNoErrors();
        $second = $contact->fresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->post("/contactos/{$contact->id}", ['_method' => 'put', 'name' => 'Dra. Pérez', 'remove_photo' => 1])->assertSessionHasNoErrors();
        $this->assertNull($contact->fresh()->photo_path);
        Storage::disk('local')->assertMissing($second);

        // Editar sin tocar la foto no la borra.
        $this->post("/contactos/{$contact->id}", ['_method' => 'put', 'name' => 'Dra. Pérez', 'photo' => UploadedFile::fake()->image('c.jpg', 300, 300)]);
        $kept = $contact->fresh()->photo_path;
        $this->put("/contactos/{$contact->id}", ['name' => 'Dra. P.'])->assertSessionHasNoErrors();
        $this->assertSame($kept, $contact->fresh()->photo_path);
    }

    public function test_invalid_photos_are_rejected(): void
    {
        $this->post('/contactos', ['name' => 'C', 'photo' => UploadedFile::fake()->createWithContent('virus.jpg', '<?php echo 1;')])->assertSessionHasErrors('photo');
        $this->post('/contactos', ['name' => 'C', 'photo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');
        $this->post('/contactos', ['name' => 'C', 'photo' => UploadedFile::fake()->image('big.jpg')->size(11000)])->assertSessionHasErrors('photo');
        $this->assertSame(0, Contact::count());
    }

    public function test_deleting_and_restoring_a_contact_keeps_its_photo(): void
    {
        $this->post('/contactos', ['name' => 'C', 'photo' => UploadedFile::fake()->image('a.jpg', 400, 400)]);
        $contact = Contact::firstOrFail();

        $this->delete("/contactos/{$contact->id}")->assertSessionHas('undo.url');
        Storage::disk('local')->assertExists($contact->photo_path);
        $this->post("/contactos/{$contact->id}/restaurar");
        $this->get("/contactos/{$contact->id}/foto")->assertOk();
    }

    public function test_full_backups_include_contact_photos(): void
    {
        $this->post('/contactos', ['name' => 'C', 'photo' => UploadedFile::fake()->image('a.jpg', 400, 400)]);
        $path = Contact::firstOrFail()->photo_path;
        $zip = sys_get_temp_dir().'/kidu-contact-'.uniqid().'.zip';

        app(BackupService::class)->create($zip, withFiles: true);

        $z = new \ZipArchive;
        $z->open($zip);
        $this->assertNotFalse($z->locateName("files/{$path}"));
        $this->assertStringContainsString('work_phone', (string) $z->getFromName('data/contacts.jsonl'));
        $z->close();
        @unlink($zip);
    }

    public function test_the_links_are_built_by_the_front_end_helpers(): void
    {
        $helpers = file_get_contents(base_path('resources/js/lib/contactLinks.js'));

        $this->assertStringContainsString('https://wa.me/', $helpers);
        $this->assertStringContainsString('https://waze.com/ul?q=', $helpers);
        $this->assertStringContainsString('instagram.com', $helpers);
        $this->assertStringContainsString('tel:', $helpers);
    }
}
