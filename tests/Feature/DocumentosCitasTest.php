<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Models\Appointment;
use App\Models\AppointmentAttachment;
use App\Models\Caretaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentosCitasTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    private Appointment $visit;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00');
        Storage::fake('local');
        $this->mama = $this->newFamily('Ruiz', 'Sofía');
        $this->actingAs($this->mama);
        $this->visit = $this->appointment($this->mama, 'Control de los 4 meses', 'Dra. Pérez');
    }

    private function newFamily(string $name, string $baby): Caretaker
    {
        return app(SetupFamily::class)->handle([
            'family_name' => $name, 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => $baby, 'baby_birth_date' => '2026-06-01',
        ]);
    }

    private function appointment(Caretaker $who, string $reason, string $doctor): Appointment
    {
        $baby = $who->family->babies()->first();

        return Appointment::create([
            'family_id' => $baby->family_id, 'baby_id' => $baby->id, 'created_by' => $who->id,
            'reason' => $reason, 'doctor_name' => $doctor, 'scheduled_at' => '2026-11-03 14:00:00', 'status' => 'scheduled',
        ]);
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    }

    public function test_each_file_is_saved_with_its_own_type_and_description(): void
    {
        $this->post("/citas/{$this->visit->id}/archivos", [
            'files' => [$this->pdf('a.pdf'), $this->pdf('b.pdf')],
            'categories' => ['prescription', ''],
            'descriptions' => ['Receta de amoxicilina', ''],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $files = AppointmentAttachment::orderBy('id')->get();
        $this->assertSame('prescription', $files[0]->category);
        $this->assertSame('Receta de amoxicilina', $files[0]->description);
        $this->assertNull($files[1]->category);
        $this->assertNull($files[1]->description);

        $this->get("/citas/{$this->visit->id}")->assertInertia(fn (Assert $page) => $page
            ->where('appointment.files.0.description', 'Receta de amoxicilina')->where('appointment.files.0.category', 'prescription'));
    }

    public function test_upload_without_metadata_still_works_and_rejects_unknown_types(): void
    {
        $this->post("/citas/{$this->visit->id}/archivos", ['files' => [$this->pdf('a.pdf')]])->assertSessionHasNoErrors();
        $this->post("/citas/{$this->visit->id}/archivos", ['files' => [$this->pdf('b.pdf')], 'categories' => ['selfie']])->assertSessionHasErrors('categories.0');
        $this->post("/citas/{$this->visit->id}/archivos", ['files' => [$this->pdf('c.pdf')], 'descriptions' => [str_repeat('x', 501)]])->assertSessionHasErrors('descriptions.0');
        $this->assertSame(1, AppointmentAttachment::count());
    }

    public function test_description_and_type_can_be_edited_later(): void
    {
        $this->post("/citas/{$this->visit->id}/archivos", ['files' => [$this->pdf('a.pdf')]]);
        $file = AppointmentAttachment::firstOrFail();

        $this->patch("/citas/{$this->visit->id}/archivos/{$file->id}", ['description' => 'Hemograma completo', 'category' => 'exam'])->assertRedirect();

        $this->assertSame('Hemograma completo', $file->fresh()->description);
        $this->assertSame('exam', $file->fresh()->category);

        $this->patch("/citas/{$this->visit->id}/archivos/{$file->id}", ['description' => '', 'category' => ''])->assertRedirect();
        $this->assertNull($file->fresh()->description);
    }

    public function test_documents_page_searches_description_filename_and_visit_data(): void
    {
        $other = $this->appointment($this->mama, 'Vacunas', 'Dr. Gómez');
        $this->post("/citas/{$this->visit->id}/archivos", ['files' => [$this->pdf('scan001.pdf'), $this->pdf('scan002.pdf')], 'categories' => ['prescription', 'exam'], 'descriptions' => ['Amoxicilina 5 ml', 'Hemograma']]);
        $this->post("/citas/{$other->id}/archivos", ['files' => [$this->pdf('carnet.pdf')], 'descriptions' => ['Carné de vacunas']]);

        $this->get('/documentos')->assertInertia(fn (Assert $page) => $page->component('Documentos')->where('total', 3)->has('files', 3));
        $this->get('/documentos?q=amoxicilina')->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('files.0.name', 'scan001.pdf'));
        $this->get('/documentos?q=scan002')->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('files.0.description', 'Hemograma'));
        $this->get('/documentos?q=pérez')->assertInertia(fn (Assert $page) => $page->where('total', 2));
        $this->get('/documentos?q=gómez carné')->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('files.0.name', 'carnet.pdf'));
        $this->get('/documentos?tipo=exam')->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('tipo', 'exam'));
        $this->get('/documentos?q=nada')->assertInertia(fn (Assert $page) => $page->where('total', 0)->has('files', 0));
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $this->post("/citas/{$this->visit->id}/archivos", ['files' => [$this->pdf('a.pdf')], 'descriptions' => ['Dosis 100%']]);

        $this->get('/documentos?q=%25')->assertInertia(fn (Assert $page) => $page->where('total', 1));
        $this->get('/documentos?q=_')->assertInertia(fn (Assert $page) => $page->where('total', 0));
    }

    public function test_documents_belong_to_the_active_baby_and_family(): void
    {
        $this->post("/citas/{$this->visit->id}/archivos", ['files' => [$this->pdf('a.pdf')], 'descriptions' => ['Secreto']]);
        $file = AppointmentAttachment::firstOrFail();

        $this->actingAs($this->newFamily('Pérez', 'Luis'));

        $this->get('/documentos?q=secreto')->assertInertia(fn (Assert $page) => $page->where('total', 0));
        $this->patch("/citas/{$this->visit->id}/archivos/{$file->id}", ['description' => 'Hackeado'])->assertNotFound();
        $this->assertSame('Secreto', $file->fresh()->description);
    }

    public function test_login_required(): void
    {
        auth()->logout();
        $this->get('/documentos')->assertRedirect();
    }
}
