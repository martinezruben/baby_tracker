<?php

namespace Tests\Feature;

use App\Contracts\ReminderChannel;
use App\Enums\AckScope;
use App\Enums\NotificationEventType;
use App\Enums\OccurrenceStatus;
use App\Enums\TargetStatus;
use App\Models\Caretaker;
use App\Models\Family;
use App\Models\FamilySettings;
use App\Models\NotificationRule;
use App\Models\NotificationTarget;
use App\Services\Notifications\NotificationActions;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class RecordatoriosTest extends TestCase
{
    use RefreshDatabase;

    private Family $family;

    private Caretaker $mama;

    private Caretaker $papa;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00');
        config(['reminders.channels.spy' => SpyChannel::class]);
        SpyChannel::$sent = [];
        SpyChannel::$fail = false;

        $this->family = Family::create(['slug' => 'ruiz', 'name' => 'Ruiz']);
        FamilySettings::create(['family_id' => $this->family->id, 'timezone' => 'UTC']);
        $this->mama = $this->family->caretakers()->create(['login_id' => 'mama', 'name' => 'Mamá']);
        $this->papa = $this->family->caretakers()->create(['login_id' => 'papa', 'name' => 'Papá']);
    }

    private function rule(array $overrides = []): NotificationRule
    {
        $rule = NotificationRule::create($overrides + [
            'family_id' => $this->family->id,
            'event_type' => NotificationEventType::Medicine,
            'title' => 'Amoxicilina',
            'offsets_minutes' => [0],
            'snooze_options' => [5, 10, 15, 30],
            'channels' => ['spy'],
            'requires_ack' => false,
            'ack_scope' => AckScope::Any,
        ]);
        $rule->recipients()->attach([$this->mama->id, $this->papa->id]);

        return $rule;
    }

    private function dispatch(): int
    {
        return app(NotificationDispatcher::class)->dispatchDue();
    }

    public function test_multiple_offsets_create_one_occurrence_each_and_are_idempotent(): void
    {
        $rule = $this->rule(['offsets_minutes' => [-1440, -60, 0]]);
        $event = now()->addDays(2);

        $scheduler = app(NotificationScheduler::class);
        $scheduler->schedule($rule, $event);
        $scheduler->schedule($rule, $event);

        $this->assertDatabaseCount('notification_occurrences', 3);
        $this->assertDatabaseCount('notification_targets', 6);
    }

    public function test_notice_is_sent_only_when_due_and_once_without_repeat(): void
    {
        $rule = $this->rule(['offsets_minutes' => [-60, 0]]);
        app(NotificationScheduler::class)->schedule($rule, now()->addHours(2));

        $this->assertSame(0, $this->dispatch());

        Carbon::setTestNow(now()->addHour());
        $this->assertSame(2, $this->dispatch());
        $this->assertCount(2, SpyChannel::$sent);

        $this->assertSame(0, $this->dispatch());
        Carbon::setTestNow(now()->addHour());
        $this->assertSame(2, $this->dispatch());
        $this->assertCount(4, SpyChannel::$sent);
    }

    public function test_repeats_up_to_max_repeats(): void
    {
        $rule = $this->rule(['repeat_every_minutes' => 10, 'max_repeats' => 2]);
        app(NotificationScheduler::class)->schedule($rule, now());

        foreach ([0, 10, 20, 30, 40] as $minutes) {
            Carbon::setTestNow(Carbon::parse('2026-11-03 12:00:00')->addMinutes($minutes));
            $this->dispatch();
        }

        // 1 envío inicial + 2 repeticiones, para cada uno de los 2 destinatarios.
        $this->assertCount(6, SpyChannel::$sent);
        $this->assertSame(TargetStatus::Sent, NotificationTarget::first()->status);
    }

    public function test_insistent_rule_repeats_until_acknowledged_and_one_ack_stops_everyone(): void
    {
        $rule = $this->rule(['repeat_every_minutes' => 5, 'requires_ack' => true]);
        $occurrence = app(NotificationScheduler::class)->schedule($rule, now())->first();

        $this->dispatch();
        Carbon::setTestNow(now()->addMinutes(5));
        $this->dispatch();
        $this->assertCount(4, SpyChannel::$sent);

        $target = $occurrence->targets()->where('caretaker_id', $mama = $this->mama->id)->first();
        app(NotificationActions::class)->acknowledge($target, $this->mama);

        Carbon::setTestNow(now()->addMinutes(5));
        $this->assertSame(0, $this->dispatch());
        $this->assertCount(4, SpyChannel::$sent);

        $this->assertSame(OccurrenceStatus::Acknowledged, $occurrence->fresh()->status);
        $this->assertSame(
            [TargetStatus::Acknowledged, TargetStatus::Acknowledged],
            $occurrence->targets()->get()->pluck('status')->all(),
        );
        $this->assertSame($mama, $occurrence->targets()->first()->acknowledged_by);
    }

    public function test_each_scope_only_stops_the_caretaker_who_accepts(): void
    {
        $rule = $this->rule(['repeat_every_minutes' => 5, 'requires_ack' => true, 'ack_scope' => AckScope::Each]);
        $occurrence = app(NotificationScheduler::class)->schedule($rule, now())->first();
        $this->dispatch();

        $target = $occurrence->targets()->where('caretaker_id', $this->mama->id)->first();
        app(NotificationActions::class)->acknowledge($target, $this->mama);

        Carbon::setTestNow(now()->addMinutes(5));
        $this->assertSame(1, $this->dispatch());
        $this->assertSame($this->papa->id, collect(SpyChannel::$sent)->last()['caretaker_id']);
        $this->assertSame(OccurrenceStatus::Active, $occurrence->fresh()->status);
    }

    public function test_snooze_delays_the_notice_and_only_allows_configured_minutes(): void
    {
        $rule = $this->rule(['repeat_every_minutes' => 60, 'requires_ack' => true]);
        $occurrence = app(NotificationScheduler::class)->schedule($rule, now())->first();
        $this->dispatch();
        SpyChannel::$sent = [];

        $target = $occurrence->targets()->first();
        app(NotificationActions::class)->snooze($target, 15);

        Carbon::setTestNow(now()->addMinutes(14));
        $this->assertSame(0, $this->dispatch());

        Carbon::setTestNow(now()->addMinutes(1));
        $this->assertSame(2, $this->dispatch());

        $this->expectException(InvalidArgumentException::class);
        app(NotificationActions::class)->snooze($target->fresh(), 7);
    }

    public function test_quiet_hours_defer_the_notice_to_the_end_of_the_window(): void
    {
        $rule = $this->rule(['quiet_start' => '22:00', 'quiet_end' => '07:00']);
        Carbon::setTestNow('2026-11-03 23:30:00');
        app(NotificationScheduler::class)->schedule($rule, now());

        $this->dispatch();
        $this->assertCount(0, SpyChannel::$sent);

        Carbon::setTestNow('2026-11-04 06:59:00');
        $this->assertSame(0, $this->dispatch());

        Carbon::setTestNow('2026-11-04 07:00:00');
        $this->dispatch();
        $this->assertCount(2, SpyChannel::$sent);
    }

    public function test_critical_rule_can_ignore_quiet_hours(): void
    {
        $rule = $this->rule(['quiet_start' => '22:00', 'quiet_end' => '07:00', 'respect_quiet_hours' => false]);
        Carbon::setTestNow('2026-11-03 23:30:00');
        app(NotificationScheduler::class)->schedule($rule, now());

        $this->dispatch();

        $this->assertCount(2, SpyChannel::$sent);
    }

    public function test_complete_event_cancels_pending_notices(): void
    {
        $rule = $this->rule(['offsets_minutes' => [-30, 0]]);
        $event = now()->addHour();
        app(NotificationScheduler::class)->schedule($rule, $event);

        app(NotificationScheduler::class)->completeEvent($rule, $event);

        Carbon::setTestNow(now()->addHours(2));
        $this->assertSame(0, $this->dispatch());
        $this->assertCount(0, SpyChannel::$sent);
    }

    public function test_failed_delivery_is_retried_then_given_up(): void
    {
        SpyChannel::$fail = true;
        $rule = $this->rule();
        $occurrence = app(NotificationScheduler::class)->schedule($rule, now())->first();

        foreach ([0, 5, 10] as $minutes) {
            Carbon::setTestNow(Carbon::parse('2026-11-03 12:00:00')->addMinutes($minutes));
            $this->dispatch();
        }

        $this->assertSame(TargetStatus::Failed, $occurrence->targets()->first()->status);
        $this->assertSame(3, $occurrence->targets()->first()->deliveries()->count());
        $this->assertSame(OccurrenceStatus::Finished, $occurrence->fresh()->status);
    }

    public function test_inactive_rules_do_not_send(): void
    {
        $rule = $this->rule(['is_active' => false]);
        app(NotificationScheduler::class)->schedule($rule, now());

        $this->assertSame(0, $this->dispatch());
    }
}

class SpyChannel implements ReminderChannel
{
    public static array $sent = [];

    public static bool $fail = false;

    public function send(Caretaker $caretaker, array $payload): void
    {
        if (self::$fail) {
            throw new RuntimeException('canal caído');
        }

        self::$sent[] = ['caretaker_id' => $caretaker->id] + $payload;
    }
}
