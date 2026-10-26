<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\DiaperType;
use App\Enums\FeedType;
use App\Enums\NotificationEventType;
use App\Enums\SleepType;
use App\Models\Baby;
use App\Models\Caretaker;
use App\Models\DiaperLog;
use App\Models\FeedLog;
use App\Models\Medicine;
use App\Models\MedicineLog;
use App\Models\NotificationRule;
use App\Models\SleepLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PanelInicioTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        // 12:00 UTC = 08:00 en Santo Domingo (UTC-4)
        Carbon::setTestNow('2026-11-03 12:00:00');

        $this->mama = $this->newFamily('Ruiz');
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

    private function baby(?Caretaker $who = null): Baby
    {
        return ($who ?? $this->mama)->family->babies()->first();
    }

    private function owner(?Caretaker $who = null): array
    {
        $who ??= $this->mama;

        return ['family_id' => $who->family_id, 'baby_id' => $this->baby($who)->id, 'caretaker_id' => $who->id];
    }

    private function diaper(string $at, array $extra = []): DiaperLog
    {
        return DiaperLog::create($this->owner() + ['occurred_at' => $at, 'type' => DiaperType::Wet] + $extra);
    }

    // ---- Panel -----------------------------------------------------------

    public function test_today_uses_the_family_day_not_the_servers(): void
    {
        $this->diaper('2026-11-03 03:59:00'); // 23:59 del día 2 en la familia: ayer
        $this->diaper('2026-11-03 04:00:00'); // 00:00 del día 3: hoy
        $this->diaper('2026-11-03 11:00:00'); // hoy

        $this->actingAs($this->mama)->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Inicio')
            ->where('summary.range', 'hoy')
            ->where('summary.diaper', 2)
            ->where('today.diapers', 2)
            ->where('clock.now', '2026-11-03T08:00'));
    }

    public function test_summary_adds_up_feeds_sleep_and_builds_the_donut_segments(): void
    {
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 06:00:00', 'type' => FeedType::Bottle, 'amount' => 120, 'unit' => 'ml']);
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 09:00:00', 'type' => FeedType::Bottle, 'amount' => 90, 'unit' => 'ml']);
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 10:00:00', 'type' => FeedType::Breast, 'duration_seconds' => 600]);
        SleepLog::create($this->owner() + ['started_at' => '2026-11-03 05:00:00', 'ended_at' => '2026-11-03 06:30:00', 'duration_minutes' => 90, 'type' => SleepType::Nap]);
        SleepLog::create($this->owner() + ['started_at' => '2026-11-03 08:00:00', 'ended_at' => '2026-11-03 08:40:00', 'duration_minutes' => 40, 'type' => SleepType::Nap]);
        $this->diaper('2026-11-03 07:00:00');
        $medicine = Medicine::create(['family_id' => $this->mama->family_id, 'name' => 'Vitamina D']);
        MedicineLog::create($this->owner() + ['medicine_id' => $medicine->id, 'occurred_at' => '2026-11-03 09:30:00', 'dose' => 1]);

        $this->actingAs($this->mama)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('summary.feed', ['count' => 3, 'amount' => 210, 'unit' => 'ml'])
            ->where('summary.sleep', ['count' => 2, 'minutes' => 130])
            ->where('summary.total', 7)
            ->where('summary.segments.0', ['key' => 'feed', 'count' => 3])
            ->where('today', ['feeds' => 3, 'diapers' => 1, 'sleep_minutes' => 130, 'total' => 7]));
    }

    public function test_week_and_month_ranges_include_older_records_but_today_does_not(): void
    {
        $this->diaper('2026-11-03 11:00:00');
        $this->diaper('2026-10-30 11:00:00'); // 4 días atrás
        $this->diaper('2026-10-10 11:00:00'); // 24 días atrás
        $this->diaper('2026-09-01 11:00:00'); // fuera de los 30 días

        $count = fn (string $rango) => $this->actingAs($this->mama)->get("/?rango={$rango}")->viewData('page')['props']['summary']['diaper'];

        $this->assertSame(1, $count('hoy'));
        $this->assertSame(2, $count('semana'));
        $this->assertSame(3, $count('mes'));
        $this->assertSame(1, $count('cualquier-cosa')); // rango inválido = hoy
    }

    public function test_deleted_records_and_other_babies_or_families_are_not_counted(): void
    {
        $this->diaper('2026-11-03 11:00:00')->delete();
        $this->diaper('2026-11-03 10:00:00');

        $other = $this->mama->family->babies()->create(['first_name' => 'Mateo', 'birth_date' => '2025-01-01']);
        DiaperLog::create(['family_id' => $this->mama->family_id, 'baby_id' => $other->id, 'occurred_at' => '2026-11-03 10:00:00', 'type' => DiaperType::Wet]);

        $stranger = $this->newFamily('Otra');
        DiaperLog::create($this->owner($stranger) + ['occurred_at' => '2026-11-03 10:00:00', 'type' => DiaperType::Wet]);

        $this->actingAs($this->mama)->get('/')->assertInertia(fn (Assert $page) => $page->where('summary.diaper', 1));
    }

    public function test_sessions_show_the_open_sleep_and_the_running_breastfeed(): void
    {
        $this->actingAs($this->mama);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('sessions', ['breast' => null, 'sleep' => null]));

        SleepLog::create($this->owner() + ['started_at' => '2026-11-03 10:00:00', 'type' => SleepType::Nap]);
        $this->post('/registrar/pecho', ['side' => 'left'])->assertRedirect('/');
        Carbon::setTestNow('2026-11-03 12:07:12');

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('sessions.sleep.started_at', fn ($v) => str_starts_with($v, '2026-11-03T10:00:00'))
            ->where('sessions.breast.side', 'left')
            ->where('sessions.breast.paused', false)
            ->where('sessions.breast.left_seconds', 432) // 7 min 12 s corriendo
            ->where('sessions.breast.right_seconds', 0));
    }

    public function test_upcoming_lists_only_active_future_reminders_in_order(): void
    {
        $mk = fn (string $title, string $at, bool $active = true) => NotificationRule::create([
            'family_id' => $this->mama->family_id, 'event_type' => NotificationEventType::Custom, 'title' => $title,
            'event_at' => $at, 'offsets_minutes' => [0], 'snooze_options' => [10], 'channels' => ['push'], 'is_active' => $active,
        ]);
        $mk('Tarde', '2026-11-05 15:00:00');
        $mk('Pronto', '2026-11-03 22:00:00');
        $mk('Pasado', '2026-11-02 22:00:00');
        $mk('Pausado', '2026-11-04 22:00:00', false);

        $this->actingAs($this->mama)->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('upcoming', 2)
            ->where('upcoming.0.title', 'Pronto')
            ->where('upcoming.1.title', 'Tarde'));
    }

    public function test_the_hub_reports_the_last_record_of_each_type(): void
    {
        $this->diaper('2026-11-03 11:15:00', ['type' => DiaperType::Both]);
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 09:50:00', 'type' => FeedType::Bottle, 'amount' => 120, 'unit' => 'ml']);
        FeedLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:00:00', 'type' => FeedType::Breast]); // no cuenta como biberón

        $this->actingAs($this->mama)->get('/registrar')->assertInertia(fn (Assert $page) => $page
            ->component('Registrar/Index')
            ->where('last', fn ($last) => collect($last)->pluck('type')->sort()->values()->all() === ['biberon', 'panal'])
            ->where('last.0.summary', fn ($s) => isset($s['amount']) || isset($s['type'])));
    }

    public function test_everything_requires_login(): void
    {
        foreach (['/', '/registrar', '/registrar/panal', '/registrar/pecho'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->post('/registrar/panal', [])->assertRedirect(route('login'));
    }
}
