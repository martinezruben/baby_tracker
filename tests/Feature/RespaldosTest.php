<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\DiaperType;
use App\Models\BabyAllergen;
use App\Models\Caretaker;
use App\Models\Contact;
use App\Models\DiaperLog;
use App\Models\Family;
use App\Models\NotificationRule;
use App\Support\Backup\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RespaldosTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    private string $zip;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00');
        Storage::fake('local');
        $this->zip = sys_get_temp_dir().'/kidu-test-'.uniqid().'.zip';

        $this->mama = app(SetupFamily::class)->handle([
            'family_name' => 'Ruiz', 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => 'Sofía', 'baby_birth_date' => '2026-06-01',
        ]);
        $this->mama->family->caretakers()->create(['login_id' => 'papa', 'name' => 'Papá', 'pin' => '246802', 'role' => 'user']);
    }

    protected function tearDown(): void
    {
        @unlink($this->zip);
        parent::tearDown();
    }

    private function seedData(): void
    {
        $baby = $this->mama->family->babies()->first();
        DiaperLog::create(['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'caretaker_id' => $this->mama->id, 'occurred_at' => '2026-11-03 11:00:00', 'type' => DiaperType::Wet, 'notes' => 'Ñandú ✓']);
        Contact::create(['family_id' => $baby->family_id, 'name' => 'Dra. Pérez', 'phone' => '809 555 0100']);
        BabyAllergen::create(['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'name' => 'Maní', 'type' => 'food']);
        Storage::disk('local')->put('photos/1/a.jpg', 'FOTO-1');
        Storage::disk('local')->put('appointments/1/1/receta.pdf', 'PDF-1');
    }

    /** Vacía la base y los archivos como una instalación nueva. */
    private function wipeEverything(): void
    {
        foreach (array_reverse(app(BackupService::class)->tables()) as $table) {
            DB::table($table)->delete();
        }
        Storage::disk('local')->deleteDirectory('photos');
        Storage::disk('local')->deleteDirectory('appointments');
    }

    private function entries(): array
    {
        $z = new ZipArchive;
        $z->open($this->zip);
        $names = array_map(fn ($i) => $z->getNameIndex($i), range(0, $z->numFiles - 1));
        $z->close();

        return $names;
    }

    private function entry(string $name): string
    {
        $z = new ZipArchive;
        $z->open($this->zip);
        $data = (string) $z->getFromName($name);
        $z->close();

        return $data;
    }

    // ---- Crear --------------------------------------------------------------

    public function test_tables_are_ordered_parents_first_and_exclude_non_family_data(): void
    {
        $tables = app(BackupService::class)->tables();

        $this->assertLessThan(array_search('caretakers', $tables), array_search('families', $tables));
        $this->assertLessThan(array_search('prescription_doses', $tables), array_search('prescriptions', $tables));
        foreach (['sessions', 'cache', 'jobs', 'push_subscriptions', 'migrations', 'notification_deliveries'] as $skipped) {
            $this->assertNotContains($skipped, $tables);
        }
    }

    public function test_backup_has_manifest_data_and_never_the_pins(): void
    {
        $this->seedData();

        $manifest = app(BackupService::class)->create($this->zip, withFiles: false);

        $this->assertSame(1, $manifest['format']);
        $this->assertSame(2, $manifest['tables']['caretakers']['rows']);
        $this->assertSame(1, $manifest['tables']['diaper_logs']['rows']);
        $this->assertContains('data/contacts.jsonl', $this->entries());
        $this->assertNotContains('files/photos/1/a.jpg', $this->entries());

        $caretakers = $this->entry('data/caretakers.jsonl');
        $this->assertStringNotContainsString('"pin"', $caretakers);
        $this->assertStringNotContainsString('remember_token', $caretakers);
        $this->assertStringContainsString('Ñandú ✓', $this->entry('data/diaper_logs.jsonl'));
    }

    public function test_full_backup_includes_photos_and_visit_files(): void
    {
        $this->seedData();

        $manifest = app(BackupService::class)->create($this->zip, withFiles: true);

        $this->assertSame(2, $manifest['files']);
        $this->assertContains('files/photos/1/a.jpg', $this->entries());
        $this->assertSame('PDF-1', $this->entry('files/appointments/1/1/receta.pdf'));
    }

    // ---- Restaurar ----------------------------------------------------------

    public function test_round_trip_restores_data_files_and_lets_the_admin_choose_a_new_pin(): void
    {
        $this->seedData();
        app(BackupService::class)->create($this->zip, withFiles: true);
        $this->wipeEverything();
        $this->assertSame(0, Family::count());

        $result = app(BackupService::class)->restore($this->zip, '135792');

        $this->assertSame(['Papá'], $result['reset_pin']);
        $this->assertSame(2, $result['files']);
        $this->assertSame('Ruiz', Family::firstOrFail()->name);
        $this->assertSame('Ñandú ✓', DiaperLog::firstOrFail()->notes);
        $this->assertSame('Maní', BabyAllergen::firstOrFail()->name);
        $this->assertSame('809 555 0100', Contact::firstOrFail()->phone);
        $this->assertSame('FOTO-1', Storage::disk('local')->get('photos/1/a.jpg'));

        $mama = Caretaker::where('name', 'Mamá')->firstOrFail();
        $this->assertTrue(Hash::check('135792', $mama->pin));
        $this->assertFalse(Hash::check('482915', $mama->pin)); // el PIN viejo no viaja

        $papa = Caretaker::where('name', 'Papá')->firstOrFail();
        $this->assertFalse(Hash::check('246802', $papa->pin));
        $this->assertSame(['active_breast_feeds', 'photos'], array_values(array_intersect(['active_breast_feeds', 'photos'], app(BackupService::class)->tables())));
    }

    public function test_stale_pending_notices_are_cancelled_so_they_do_not_all_fire_after_restoring(): void
    {
        $rule = NotificationRule::create([
            'family_id' => $this->mama->family_id, 'event_type' => 'custom', 'title' => 'Vieja', 'offsets_minutes' => [0],
            'requires_ack' => false, 'ack_scope' => 'any', 'snooze_options' => [5], 'channels' => ['push'], 'event_at' => '2026-10-01 12:00:00',
        ]);
        $occurrence = $rule->occurrences()->create(['event_at' => '2026-10-01 12:00:00', 'offset_minutes' => 0, 'status' => 'active']);
        $occurrence->targets()->create(['caretaker_id' => $this->mama->id, 'status' => 'pending', 'next_send_at' => '2026-10-01 12:00:00']);
        $future = $rule->occurrences()->create(['event_at' => '2026-12-01 12:00:00', 'offset_minutes' => 0, 'status' => 'active']);
        $future->targets()->create(['caretaker_id' => $this->mama->id, 'status' => 'pending', 'next_send_at' => '2026-12-01 12:00:00']);

        app(BackupService::class)->create($this->zip, false);
        $this->wipeEverything();
        app(BackupService::class)->restore($this->zip, '135792');

        $this->assertSame(['cancelled', 'pending'], DB::table('notification_targets')->orderBy('id')->pluck('status')->all());
    }

    public function test_it_refuses_to_restore_over_existing_data_unless_wiping(): void
    {
        $this->seedData();
        app(BackupService::class)->create($this->zip, false);
        DiaperLog::query()->delete();

        $this->expectExceptionMessage(trans('ui.respaldos.errors.not_empty'));
        app(BackupService::class)->restore($this->zip, '135792');
    }

    public function test_wipe_replaces_what_is_there(): void
    {
        $this->seedData();
        app(BackupService::class)->create($this->zip, true);
        DiaperLog::create(['family_id' => $this->mama->family_id, 'baby_id' => 1, 'caretaker_id' => $this->mama->id, 'occurred_at' => '2026-11-03 11:30:00', 'type' => DiaperType::Dirty]);
        Contact::query()->delete();

        app(BackupService::class)->restore($this->zip, '135792', wipe: true);

        $this->assertSame(1, DiaperLog::count()); // el de después del respaldo ya no está
        $this->assertSame(1, Contact::count());
    }

    public function test_a_tampered_backup_is_rejected_before_touching_anything(): void
    {
        $this->seedData();
        app(BackupService::class)->create($this->zip, false);

        $z = new ZipArchive;
        $z->open($this->zip);
        $z->addFromString('data/contacts.jsonl', '{"id":99,"family_id":1,"name":"Inyectado"}'."\n");
        $z->close();
        $this->wipeEverything();

        try {
            app(BackupService::class)->restore($this->zip, '135792');
            $this->fail('Debió fallar');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('dañado', $e->getMessage());
        }
        $this->assertSame(0, Family::count());
        $this->assertSame(0, Contact::count());
    }

    public function test_zip_slip_and_foreign_files_are_rejected(): void
    {
        $this->seedData();
        app(BackupService::class)->create($this->zip, false);
        $z = new ZipArchive;
        $z->open($this->zip);
        $z->addFromString('files/photos/../../../../escape.txt', 'x');
        $z->close();
        $this->wipeEverything();

        $this->expectExceptionMessage(trans('ui.respaldos.errors.unsafe'));
        app(BackupService::class)->restore($this->zip, '135792');
    }

    public function test_non_zip_and_non_backup_files_are_rejected(): void
    {
        file_put_contents($this->zip, 'no soy un zip');
        try {
            app(BackupService::class)->restore($this->zip, '135792');
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertSame(trans('ui.respaldos.errors.not_zip'), $e->getMessage());
        }

        $z = new ZipArchive;
        $z->open($this->zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $z->addFromString('hola.txt', 'hola');
        $z->close();
        $this->expectExceptionMessage(trans('ui.respaldos.errors.not_backup'));
        app(BackupService::class)->restore($this->zip, '135792');
    }

    // ---- Comandos -----------------------------------------------------------

    public function test_backup_command_stores_a_file_and_keeps_only_the_latest(): void
    {
        foreach (['kidu-20261101-030000.zip', 'kidu-20261102-030000.zip', 'kidu-20261103-030000.zip'] as $i => $old) {
            Storage::disk('local')->put("backups/{$old}", 'x');
            touch(Storage::disk('local')->path("backups/{$old}"), now()->subDays(3 - $i)->timestamp);
        }

        $this->artisan('backup:run', ['--keep' => 2])->assertSuccessful();

        $stored = collect(app(BackupService::class)->stored())->pluck('name');
        $this->assertCount(2, $stored);
        $this->assertContains('kidu-'.now()->format('Ymd-His').'.zip', $stored);
    }

    public function test_restore_command_asks_for_the_new_pin_and_validates_it(): void
    {
        $this->seedData();
        app(BackupService::class)->create($this->zip, false);
        $this->wipeEverything();

        $this->artisan('backup:restore', ['file' => $this->zip])
            ->expectsQuestion('PIN nuevo del administrador (4 a 10 números)', 'abc')
            ->expectsOutputToContain('4 a 10 números')->assertFailed();
        $this->assertSame(0, Family::count());

        $this->artisan('backup:restore', ['file' => $this->zip])
            ->expectsQuestion('PIN nuevo del administrador (4 a 10 números)', '135792')
            ->expectsOutputToContain('Restaurado')->expectsOutputToContain('Papá')->assertSuccessful();
        $this->assertTrue(Hash::check('135792', Caretaker::where('name', 'Mamá')->firstOrFail()->pin));

        $this->artisan('backup:restore', ['file' => '/no/existe.zip'])->expectsOutputToContain('No existe')->assertFailed();
    }

    public function test_the_daily_backup_is_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('backup:run')->assertSuccessful();
    }

    // ---- Pantallas ----------------------------------------------------------

    public function test_admin_can_list_create_download_and_delete_stored_backups(): void
    {
        $this->actingAs($this->mama);

        $this->post('/ajustes/respaldos')->assertRedirect()->assertSessionHas('status');
        $name = app(BackupService::class)->stored()[0]['name'];

        $this->get('/ajustes/respaldos')->assertInertia(fn ($page) => $page->component('Ajustes/Respaldos')->has('backups', 1)->where('backups.0.name', $name));
        $this->get("/ajustes/respaldos/{$name}")->assertOk()->assertHeader('content-type', 'application/zip');

        $this->delete("/ajustes/respaldos/{$name}")->assertRedirect();
        $this->assertSame([], app(BackupService::class)->stored());
    }

    public function test_export_downloads_a_fresh_zip_that_is_not_kept(): void
    {
        $this->actingAs($this->mama);
        $this->seedData();

        $response = $this->get('/ajustes/respaldos/exportar?files=1')->assertOk();
        file_put_contents($this->zip, $response->baseResponse->getFile()->getContent());

        $this->assertContains('manifest.json', $this->entries());
        $this->assertContains('files/photos/1/a.jpg', $this->entries());
        $this->assertSame([], app(BackupService::class)->stored());
    }

    public function test_download_names_are_validated_against_path_traversal(): void
    {
        $this->actingAs($this->mama);
        Storage::disk('local')->put('secreto.zip', 'x');

        $this->get('/ajustes/respaldos/..%2Fsecreto.zip')->assertNotFound();
        $this->get('/ajustes/respaldos/secreto.zip')->assertNotFound();
        $this->delete('/ajustes/respaldos/secreto.zip')->assertNotFound();
        $this->assertTrue(Storage::disk('local')->exists('secreto.zip'));
    }

    public function test_only_admins_reach_backups(): void
    {
        $this->actingAs(Caretaker::where('name', 'Papá')->firstOrFail());

        $this->get('/ajustes/respaldos')->assertForbidden();
        $this->post('/ajustes/respaldos')->assertForbidden();
        $this->get('/ajustes/respaldos/exportar')->assertForbidden();

        auth()->logout();
        $this->get('/ajustes/respaldos')->assertRedirect();
    }

    public function test_restore_screen_only_exists_on_a_fresh_installation_and_works_end_to_end(): void
    {
        $this->seedData();
        app(BackupService::class)->create($this->zip, true);

        // Con datos: no hay pantalla de restaurar.
        $this->get('/configuracion/restaurar')->assertRedirect(route('login'));
        $this->post('/configuracion/restaurar', [])->assertRedirect(route('login'));

        $this->wipeEverything();
        auth()->logout();

        $this->get('/configuracion/restaurar')->assertOk();
        $upload = new UploadedFile($this->zip, 'respaldo.zip', 'application/zip', null, true);

        $this->post('/configuracion/restaurar', ['backup' => $upload, 'pin' => '135792', 'pin_confirmation' => '135792'])
            ->assertRedirect(route('login'))->assertSessionHas('restored.rows');

        $this->assertSame('Ruiz', Family::firstOrFail()->name);
        $this->assertTrue(Hash::check('135792', Caretaker::where('name', 'Mamá')->firstOrFail()->pin));
    }

    public function test_restore_screen_validates_the_pin_and_the_file(): void
    {
        $this->wipeEverything();
        auth()->logout();
        file_put_contents($this->zip, 'no soy un zip');
        $upload = new UploadedFile($this->zip, 'respaldo.zip', 'application/zip', null, true);

        $this->post('/configuracion/restaurar', ['backup' => $upload, 'pin' => '1234', 'pin_confirmation' => '1234'])->assertSessionHasErrors('pin'); // PIN débil
        $this->post('/configuracion/restaurar', ['backup' => $upload, 'pin' => '135792', 'pin_confirmation' => '135792'])->assertSessionHasErrors('backup');
        $this->post('/configuracion/restaurar', ['pin' => '135792', 'pin_confirmation' => '135792'])->assertSessionHasErrors('backup');
        $this->assertSame(0, Family::count());
    }

    public function test_a_failed_backup_tells_the_admin_the_real_reason(): void
    {
        $this->actingAs($this->mama);
        $this->mock(BackupService::class, function ($mock) {
            $mock->shouldReceive('newName')->andReturn('kidu-20261103-120000.zip');
            $mock->shouldReceive('path')->andReturn('/x/kidu-20261103-120000.zip');
            $mock->shouldReceive('create')->andThrow(new \RuntimeException('No se puede escribir en /app/storage'));
        });

        $this->post('/ajustes/respaldos')->assertSessionHasErrors(['backup' => 'No se pudo crear el respaldo. Motivo: No se puede escribir en /app/storage']);
    }

    public function test_the_work_dir_is_writable_and_a_backup_can_be_created_into_a_new_folder(): void
    {
        $service = app(BackupService::class);
        $this->assertTrue(is_writable($service->workDir()));

        $target = $service->workDir().'/nueva-'.uniqid().'/kidu-test.zip';
        $service->create($target, false);

        $this->assertFileExists($target);
        @unlink($target);
        @rmdir(dirname($target));
    }
}
