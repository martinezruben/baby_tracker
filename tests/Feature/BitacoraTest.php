<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\DiaperType;
use App\Enums\FeedType;
use App\Enums\MeasurementType;
use App\Enums\SleepType;
use App\Models\Caretaker;
use App\Models\DiaperLog;
use App\Models\FeedLog;
use App\Models\Measurement;
use App\Models\Medicine;
use App\Models\MedicineLog;
use App\Models\Note;
use App\Models\SleepLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BitacoraTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00');
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

    private function owner(?Caretaker $who = null): array
    {
        $who ??= $this->mama;

        return ['family_id' => $who->family_id, 'baby_id' => $who->family->babies()->first()->id, 'caretaker_id' => $who->id];
    }

    private function diaper(string $at): DiaperLog
    {
        return DiaperLog::create($this->owner() + ['occurred_at' => $at, 'type' => DiaperType::Wet]);
    }

    private function load(string $query = ''): array
    {
        return $this->get('/bitacora'.$query)->viewData('page')['props'];
    }

    public function test_it_mixes_every_type_newest_first_with_the_data_the_screen_needs(): void
    {
        $vit = Medicine::create(['family_id' => $this->mama->family_id, 'name' => 'Vitamina D']);

        $this->diaper('2026-11-03 07:00:00');
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 08:00:00', 'type' => FeedType::Bottle, 'amount' => 120, 'unit' => 'ml']);
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 09:00:00', 'type' => FeedType::Breast, 'duration_seconds' => 720, 'left_seconds' => 420, 'right_seconds' => 300]);
        SleepLog::create($this->owner() + ['started_at' => '2026-11-03 10:00:00', 'type' => SleepType::Nap]);
        MedicineLog::create($this->owner() + ['medicine_id' => $vit->id, 'occurred_at' => '2026-11-03 10:30:00', 'dose' => 1, 'unit' => 'gota']);
        Note::create($this->owner() + ['occurred_at' => '2026-11-03 11:00:00', 'content' => 'Primera sonrisa']);
        Measurement::create($this->owner() + ['measured_on' => '2026-11-03', 'type' => MeasurementType::Weight, 'value' => 6.1, 'unit' => 'kg']);

        $props = $this->load();

        $this->assertSame(
            ['medida', 'nota', 'medicina', 'sueno', 'pecho', 'biberon', 'panal'],
            collect($props['entries'])->pluck('type')->all(),
        );
        $this->assertNull($props['next']);

        $byType = collect($props['entries'])->keyBy('type');
        $this->assertSame('Mamá', $byType['panal']['by']);
        $this->assertSame(['name' => 'Vitamina D', 'dose' => 1.0, 'unit' => 'gota'], $byType['medicina']['data']);
        $this->assertTrue($byType['sueno']['data']['open']);
        $this->assertSame(['seconds' => 720, 'left' => 420, 'right' => 300, 'side' => null], $byType['pecho']['data']);
        $this->assertSame('Primera sonrisa', $byType['nota']['data']['content']);
    }

    public function test_bottle_and_breast_are_separate_types_that_share_a_table(): void
    {
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 08:00:00', 'type' => FeedType::Bottle, 'amount' => 90, 'unit' => 'ml']);
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 09:00:00', 'type' => FeedType::Breast, 'duration_seconds' => 300]);

        $this->assertSame(['biberon'], collect($this->load('?tipo=biberon')['entries'])->pluck('type')->all());
        $this->assertSame(['pecho'], collect($this->load('?tipo=pecho')['entries'])->pluck('type')->all());
        $this->assertCount(2, $this->load('?tipo=inventado')['entries']); // tipo inválido = todo
    }

    public function test_pagination_by_cursor_never_loses_or_repeats_entries_even_with_ties(): void
    {
        // 35 registros; 10 de ellos con el mismo segundo exacto, justo en el borde de la página.
        foreach (range(1, 25) as $i) {
            $this->diaper(Carbon::parse('2026-11-03 06:00:00')->addMinutes($i));
        }
        foreach (range(1, 5) as $i) {
            $this->diaper('2026-11-03 06:00:00');
            Note::create($this->owner() + ['occurred_at' => '2026-11-03 06:00:00', 'content' => "n{$i}"]);
        }

        $seen = [];
        $cursor = null;
        $pages = 0;

        do {
            $props = $this->load($cursor ? '?antes='.urlencode($cursor) : '');
            foreach ($props['entries'] as $e) {
                $seen[] = $e['type'].$e['id'];
            }
            $cursor = $props['next'];
            $pages++;
        } while ($cursor && $pages < 5);

        $this->assertSame(35, count($seen));
        $this->assertSame(35, count(array_unique($seen)));
        $this->assertSame(2, $pages);
    }

    public function test_it_only_shows_the_active_babys_records_and_skips_deleted_ones(): void
    {
        $this->diaper('2026-11-03 07:00:00');
        $this->diaper('2026-11-03 08:00:00')->delete();

        $other = $this->mama->family->babies()->create(['first_name' => 'Mateo', 'birth_date' => '2025-01-01']);
        DiaperLog::create(['family_id' => $this->mama->family_id, 'baby_id' => $other->id, 'occurred_at' => '2026-11-03 09:00:00', 'type' => DiaperType::Wet]);

        $stranger = $this->newFamily('Otra');
        DiaperLog::create($this->owner($stranger) + ['occurred_at' => '2026-11-03 09:30:00', 'type' => DiaperType::Wet]);

        $this->assertCount(1, $this->load()['entries']);

        $this->post("/bebes/{$other->id}/seleccionar");
        $this->assertCount(1, $this->load()['entries']);
    }

    public function test_page_is_an_inertia_page_for_logged_in_users_only(): void
    {
        $this->get('/bitacora')->assertInertia(fn (Assert $page) => $page->component('Bitacora')->where('tipo', null)->has('types', 12));

        auth()->logout();
        $this->get('/bitacora')->assertRedirect(route('login'));
    }

    public function test_deleting_from_the_log_offers_to_restore_and_restore_brings_it_back(): void
    {
        $log = $this->diaper('2026-11-03 07:00:00');

        $this->delete("/registros/panal/{$log->id}?desde=bitacora")
            ->assertSessionHas('undo.message', 'Registro eliminado')
            ->assertSessionHas('undo.method', 'post');
        $this->assertSoftDeleted($log);
        $restoreUrl = session('undo.url'); // el aviso se consume en la siguiente petición
        $this->assertCount(0, $this->load()['entries']);

        $this->post($restoreUrl)->assertRedirect();
        $this->assertNotSoftDeleted($log);
        $this->assertCount(1, $this->load()['entries']);
    }

    public function test_breast_feeds_can_be_deleted_and_restored_from_the_log(): void
    {
        $feed = FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 09:00:00', 'type' => FeedType::Breast, 'duration_seconds' => 300]);

        $this->delete("/registros/pecho/{$feed->id}?desde=bitacora");
        $this->assertSoftDeleted($feed);

        $this->post("/registros/pecho/{$feed->id}/restaurar");
        $this->assertNotSoftDeleted($feed);
    }

    public function test_another_family_cannot_delete_or_restore_your_records(): void
    {
        $log = $this->diaper('2026-11-03 07:00:00');
        $log->delete();

        $this->actingAs($this->newFamily('Otra'))->post("/registros/panal/{$log->id}/restaurar")->assertNotFound();
        $this->assertSoftDeleted($log);

        $this->actingAs($this->mama)->post("/registros/panal/{$log->id}/restaurar");
        $this->actingAs($this->newFamily('Otra2'))->delete("/registros/panal/{$log->id}?desde=bitacora")->assertNotFound();
        $this->assertNotSoftDeleted($log);
    }
}
