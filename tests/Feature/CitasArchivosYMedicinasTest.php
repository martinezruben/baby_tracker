<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\DoseStatus;
use App\Enums\OccurrenceStatus;
use App\Enums\PrescriptionStatus;
use App\Models\Appointment;
use App\Models\Caretaker;
use App\Models\Medicine;
use App\Models\MedicineLog;
use App\Models\NotificationRule;
use App\Models\Prescription;
use App\Models\PrescriptionDose;
use App\Models\PushSubscription;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\Push\PushClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePushClient;
use Tests\TestCase;

class CitasArchivosYMedicinasTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    private Caretaker $papa;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00'); // 08:00 local (UTC-4)
        Storage::fake('local');

        $this->mama = $this->newFamily('Ruiz');
        $this->papa = $this->mama->family->caretakers()->create(['login_id' => 'papa', 'name' => 'Papá', 'pin' => '246802']);
        $this->actingAs($this->mama);

        $this->appointment = Appointment::create([
            'family_id' => $this->mama->family_id, 'baby_id' => $this->mama->family->babies()->first()->id,
            'reason' => 'Fiebre', 'scheduled_at' => '2026-11-03 11:00:00', 'status' => 'completed',
        ]);
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

    private function pdf(string $name = 'receta.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    }

    private function rx(array $overrides = []): array
    {
        return $overrides + [
            'new_medicine' => 'Amoxicilina', 'dose' => 5, 'unit' => 'ml', 'interval_hours' => 8, 'total_doses' => 6,
            'starts_at' => '2026-11-03T08:00', 'instructions' => 'Con comida',
        ];
    }

    private function addRx(array $overrides = []): Prescription
    {
        $this->post("/citas/{$this->appointment->id}/medicinas", $this->rx($overrides))->assertSessionHasNoErrors();

        return Prescription::latest('id')->firstOrFail();
    }

    // ---- Archivos ----------------------------------------------------------------------

    public function test_pdfs_and_images_are_stored_privately_and_served_only_through_the_authorized_route(): void
    {
        $this->post("/citas/{$this->appointment->id}/archivos", ['files' => [$this->pdf(), UploadedFile::fake()->image('herida.jpg', 200, 200)]])
            ->assertSessionHasNoErrors();

        $files = $this->appointment->attachments()->orderBy('id')->get();
        $this->assertCount(2, $files);
        $this->assertSame('receta.pdf', $files[0]->original_name);
        $this->assertSame('application/pdf', $files[0]->mime);
        $this->assertTrue($files[1]->isImage());
        Storage::disk('local')->assertExists($files[0]->path);
        $this->assertStringStartsWith("appointments/{$this->mama->family_id}/{$this->appointment->id}/", $files[0]->path);

        $response = $this->get("/citas/{$this->appointment->id}/archivos/{$files[0]->id}")->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_only_pdf_and_images_up_to_10_mb_and_10_per_visit_are_accepted(): void
    {
        $url = "/citas/{$this->appointment->id}/archivos";

        $this->post($url, ['files' => [UploadedFile::fake()->createWithContent('malo.php', '<?php echo 1;')]])->assertSessionHasErrors('files.0');
        $this->post($url, ['files' => [UploadedFile::fake()->createWithContent('nota.txt', 'hola')]])->assertSessionHasErrors('files.0');
        $this->post($url, ['files' => [UploadedFile::fake()->image('enorme.png')->size(10241)]])->assertSessionHasErrors('files.0');
        $this->post($url, ['files' => []])->assertSessionHasErrors('files');
        $this->post($url, [])->assertSessionHasErrors('files');
        $this->assertSame(0, $this->appointment->attachments()->count());

        $this->post($url, ['files' => collect(range(1, 10))->map(fn ($i) => $this->pdf("a{$i}.pdf"))->all()])->assertSessionHasNoErrors();
        $this->post($url, ['files' => [$this->pdf('otro.pdf')]])->assertSessionHasErrors('files');
        $this->assertSame(10, $this->appointment->attachments()->count());
    }

    public function test_deleting_a_file_removes_it_from_disk(): void
    {
        $this->post("/citas/{$this->appointment->id}/archivos", ['files' => [$this->pdf()]]);
        $file = $this->appointment->attachments()->firstOrFail();

        $this->delete("/citas/{$this->appointment->id}/archivos/{$file->id}")->assertRedirect();

        $this->assertDatabaseCount('appointment_attachments', 0);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_files_are_not_reachable_from_another_family_or_through_another_appointment(): void
    {
        $this->post("/citas/{$this->appointment->id}/archivos", ['files' => [$this->pdf()]]);
        $file = $this->appointment->attachments()->firstOrFail();
        $other = Appointment::create([
            'family_id' => $this->mama->family_id, 'baby_id' => $this->appointment->baby_id,
            'reason' => 'Otra', 'scheduled_at' => '2026-11-10 11:00:00',
        ]);

        $this->get("/citas/{$other->id}/archivos/{$file->id}")->assertNotFound(); // archivo de otra cita

        $this->actingAs($this->newFamily('Otra'));
        $this->get("/citas/{$this->appointment->id}/archivos/{$file->id}")->assertNotFound();
        $this->post("/citas/{$this->appointment->id}/archivos", ['files' => [$this->pdf()]])->assertNotFound();
        $this->delete("/citas/{$this->appointment->id}/archivos/{$file->id}")->assertNotFound();
        Storage::disk('local')->assertExists($file->path);

        auth()->logout();
        $this->get("/citas/{$this->appointment->id}/archivos/{$file->id}")->assertRedirect(route('login'));
    }

    // ---- Medicinas recetadas -----------------------------------------------------------------

    public function test_a_prescription_generates_every_dose_and_a_notice_for_each_one(): void
    {
        $rx = $this->addRx();

        $this->assertSame('Amoxicilina', $rx->medicine->name);
        $this->assertSame(PrescriptionStatus::Active, $rx->status);
        $this->assertSame(6, $rx->doses()->count());
        // 08:00 local = 12:00 UTC, cada 8 h
        $this->assertSame(
            ['2026-11-03 12:00:00', '2026-11-03 20:00:00', '2026-11-04 04:00:00', '2026-11-04 12:00:00', '2026-11-04 20:00:00', '2026-11-05 04:00:00'],
            $rx->doses->map(fn ($d) => $d->scheduled_at->format('Y-m-d H:i:s'))->all(),
        );

        $rule = $rx->rule;
        $this->assertSame('Amoxicilina · 5 ml', $rule->title);
        $this->assertSame('Con comida', $rule->body);
        $this->assertTrue($rule->requires_ack);
        $this->assertSame(10, $rule->repeat_every_minutes);
        $this->assertFalse($rule->respect_quiet_hours); // una medicina no espera a la mañana
        $this->assertTrue($rule->subject->is($rx));
        $this->assertSame(6, $rule->occurrences()->count());
        $this->assertSame(12, $rule->occurrences()->first()->targets()->count() + 10); // 2 cuidadores por toma
    }

    public function test_doses_long_past_are_listed_but_do_not_blast_notifications(): void
    {
        $rx = $this->addRx(['starts_at' => '2026-11-02T08:00', 'interval_hours' => 12, 'total_doses' => 4]); // empezó ayer

        $this->assertSame(4, $rx->doses()->count());
        // Ayer 12:00 y 24:00 UTC ya pasaron; hoy 12:00 UTC es ahora; mañana 00:00 UTC es futura.
        $this->assertSame(2, $rx->rule->occurrences()->count());
    }

    public function test_giving_a_dose_logs_the_medicine_stops_its_notices_and_can_be_undone(): void
    {
        $rx = $this->addRx();
        $dose = $rx->doses()->first();

        $this->post("/dosis/{$dose->id}/dada")->assertRedirect()->assertSessionHas('undo.method', 'post');
        $undoUrl = session('undo.url');

        $dose->refresh();
        $this->assertSame(DoseStatus::Given, $dose->status);
        $this->assertSame($this->mama->id, $dose->given_by);
        $log = MedicineLog::firstOrFail();
        $this->assertSame($dose->medicine_log_id, $log->id);
        $this->assertSame('5.00', $log->dose);
        $this->assertSame($rx->medicine_id, $log->medicine_id);
        $this->assertSame(OccurrenceStatus::Cancelled, $rx->rule->occurrences()->orderBy('event_at')->first()->status);

        $this->post("/dosis/{$dose->id}/dada"); // idempotente
        $this->assertSame(1, MedicineLog::count());

        $this->post($undoUrl)->assertRedirect();
        $this->assertSame(DoseStatus::Pending, $dose->fresh()->status);
        $this->assertSoftDeleted($log);
    }

    public function test_skipping_does_not_touch_the_medicine_log_and_the_treatment_completes_when_nothing_is_pending(): void
    {
        $rx = $this->addRx(['total_doses' => 2]);

        $this->post('/dosis/'.$rx->doses[0]->id.'/omitir');
        $this->assertSame(0, MedicineLog::count());
        $this->assertSame(PrescriptionStatus::Active, $rx->fresh()->status);

        $this->post('/dosis/'.$rx->doses[1]->id.'/dada');
        $this->assertSame(PrescriptionStatus::Completed, $rx->fresh()->status);

        $this->post('/dosis/'.$rx->doses[1]->id.'/deshacer');
        $this->assertSame(PrescriptionStatus::Active, $rx->fresh()->status);
    }

    public function test_stopping_a_treatment_cancels_notices_and_skips_what_is_left(): void
    {
        $rx = $this->addRx();
        $this->post('/dosis/'.$rx->doses[0]->id.'/dada');

        $this->delete("/recetas/{$rx->id}")->assertRedirect();

        $this->assertSame(PrescriptionStatus::Cancelled, $rx->fresh()->status);
        $this->assertSame(1, $rx->doses()->where('status', DoseStatus::Given)->count());
        $this->assertSame(5, $rx->doses()->where('status', DoseStatus::Skipped)->count());
        $this->assertFalse($rx->rule->fresh()->is_active);

        Carbon::setTestNow('2026-11-06 00:00:00');
        $this->assertSame(0, app(NotificationDispatcher::class)->dispatchDue());
    }

    public function test_confirming_the_push_notice_logs_the_dose_and_stops_the_insistence_for_everyone(): void
    {
        $push = new FakePushClient;
        $this->app->instance(PushClient::class, $push);
        foreach ([$this->mama, $this->papa] as $who) {
            PushSubscription::create([
                'caretaker_id' => $who->id, 'endpoint' => "https://push.example/{$who->id}",
                'endpoint_hash' => PushSubscription::hashEndpoint("https://push.example/{$who->id}"), 'p256dh' => 'k', 'auth' => 'a',
            ]);
        }

        $rx = $this->addRx();
        app(NotificationDispatcher::class)->dispatchDue(); // llega la toma de las 08:00 a los dos
        $this->assertCount(2, $push->sent);
        $this->assertSame('Amoxicilina · 5 ml', json_decode($push->sent[0]['payload'], true)['title']);

        $ackUrl = json_decode($push->sent[0]['payload'], true)['data']['ack_url'];
        auth()->logout();
        $this->postJson($ackUrl)->assertOk(); // el botón "Confirmar" del aviso, sin sesión

        $dose = $rx->doses()->first();
        $this->assertSame(DoseStatus::Given, $dose->status);
        $this->assertSame(1, MedicineLog::count());

        Carbon::setTestNow('2026-11-03 12:30:00');
        app(NotificationDispatcher::class)->dispatchDue();
        $this->assertCount(2, $push->sent); // nada más
    }

    public function test_prescription_validation_and_isolation(): void
    {
        $url = "/citas/{$this->appointment->id}/medicinas";
        $this->post($url, $this->rx(['new_medicine' => null]))->assertSessionHasErrors(['medicine_id', 'new_medicine']);
        $this->post($url, $this->rx(['interval_hours' => 0]))->assertSessionHasErrors('interval_hours');
        $this->post($url, $this->rx(['total_doses' => 500]))->assertSessionHasErrors('total_doses');
        $this->post($url, $this->rx(['dose' => 0]))->assertSessionHasErrors('dose');
        $this->post($url, $this->rx(['starts_at' => '2026-10-30T08:00']))->assertSessionHasErrors('starts_at');
        $this->assertSame(0, Prescription::count());

        $stranger = $this->newFamily('Otra');
        $theirs = Medicine::create(['family_id' => $stranger->family_id, 'name' => 'Ajena']);
        $this->post($url, $this->rx(['new_medicine' => null, 'medicine_id' => $theirs->id]))->assertSessionHasErrors('medicine_id');

        $rx = $this->addRx();
        $this->actingAs($stranger);
        $this->post($url, $this->rx())->assertNotFound();
        $this->delete("/recetas/{$rx->id}")->assertNotFound();
        $this->post('/dosis/'.$rx->doses()->first()->id.'/dada')->assertNotFound();
        $this->post('/dosis/'.$rx->doses()->first()->id.'/omitir')->assertNotFound();
        $this->assertSame(0, MedicineLog::count());
    }

    public function test_the_show_page_lists_prescriptions_with_progress_and_the_home_shows_the_next_dose(): void
    {
        $rx = $this->addRx();
        $this->post('/dosis/'.$rx->doses[0]->id.'/dada');

        $this->get("/citas/{$this->appointment->id}")->assertInertia(fn (Assert $page) => $page
            ->where('appointment.prescriptions.0.medicine', 'Amoxicilina')
            ->where('appointment.prescriptions.0.given', 1)
            ->where('appointment.prescriptions.0.total_doses', 6)
            ->where('appointment.prescriptions.0.next_at', fn ($v) => str_starts_with($v, '2026-11-03T20:00'))
            ->has('appointment.prescriptions.0.doses', 6));

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('upcoming.0.kind', 'dose')
            ->where('upcoming.0.title', 'Amoxicilina')
            ->where('upcoming.0.href', "/citas/{$this->appointment->id}"));

        $this->assertSame(1, NotificationRule::where('subject_type', Prescription::class)->count());
        $this->assertSame(6, PrescriptionDose::count());
    }
}
