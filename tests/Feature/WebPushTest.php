<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\AckScope;
use App\Enums\NotificationEventType;
use App\Enums\TargetStatus;
use App\Models\Caretaker;
use App\Models\NotificationRule;
use App\Models\NotificationTarget;
use App\Models\PushSubscription;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationScheduler;
use App\Services\Notifications\Push\PushClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\Support\FakePushClient;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    private Caretaker $papa;

    private FakePushClient $push;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00');
        $this->push = new FakePushClient;
        $this->app->instance(PushClient::class, $this->push);

        $this->mama = app(SetupFamily::class)->handle([
            'family_name' => 'Ruiz', 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => 'Sofía', 'baby_birth_date' => '2026-06-01',
        ]);
        $this->papa = $this->mama->family->caretakers()->create(['login_id' => 'papa', 'name' => 'Papá', 'pin' => '246802']);
    }

    private function subscribe(Caretaker $who, string $endpoint = 'https://push.example/abc'): PushSubscription
    {
        return PushSubscription::create([
            'caretaker_id' => $who->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'key', 'auth' => 'auth',
        ]);
    }

    private function insistentRule(): NotificationRule
    {
        $rule = NotificationRule::create([
            'family_id' => $this->mama->family_id,
            'event_type' => NotificationEventType::Medicine,
            'title' => 'Amoxicilina',
            'body' => 'Toma de las 12:00',
            'offsets_minutes' => [0],
            'repeat_every_minutes' => 5,
            'requires_ack' => true,
            'ack_scope' => AckScope::Any,
            'snooze_options' => [5, 10, 15, 30],
            'channels' => ['push'],
        ]);
        $rule->recipients()->attach([$this->mama->id, $this->papa->id]);

        return $rule;
    }

    // ---- Suscripciones ----------------------------------------------------

    public function test_a_caretaker_can_register_and_remove_a_device(): void
    {
        $payload = ['endpoint' => 'https://push.example/xyz', 'keys' => ['p256dh' => 'pk', 'auth' => 'au'], 'contentEncoding' => 'aes128gcm'];

        $this->actingAs($this->mama)->postJson('/push/suscripciones', $payload)->assertCreated();
        $this->assertSame(1, $this->mama->pushSubscriptions()->count());

        $this->deleteJson('/push/suscripciones', ['endpoint' => 'https://push.example/xyz'])->assertNoContent();
        $this->assertSame(0, $this->mama->pushSubscriptions()->count());
    }

    public function test_registering_the_same_device_again_moves_it_to_the_current_caretaker(): void
    {
        $payload = ['endpoint' => 'https://push.example/xyz', 'keys' => ['p256dh' => 'pk', 'auth' => 'au']];

        $this->actingAs($this->mama)->postJson('/push/suscripciones', $payload)->assertCreated();
        $this->actingAs($this->papa)->postJson('/push/suscripciones', $payload)->assertCreated();

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertSame(1, $this->papa->pushSubscriptions()->count());
    }

    public function test_subscription_requires_login_https_and_keys(): void
    {
        $ok = ['endpoint' => 'https://push.example/xyz', 'keys' => ['p256dh' => 'pk', 'auth' => 'au']];

        $this->postJson('/push/suscripciones', $ok)->assertUnauthorized();

        $this->actingAs($this->mama)
            ->postJson('/push/suscripciones', ['endpoint' => 'http://insecure.example/x'] + $ok)
            ->assertJsonValidationErrors('endpoint');
        $this->postJson('/push/suscripciones', ['endpoint' => $ok['endpoint']])
            ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    }

    public function test_a_caretaker_cannot_remove_someone_elses_device(): void
    {
        $this->subscribe($this->papa);

        $this->actingAs($this->mama)->deleteJson('/push/suscripciones', ['endpoint' => 'https://push.example/abc']);

        $this->assertSame(1, $this->papa->pushSubscriptions()->count());
    }

    // ---- Canal ------------------------------------------------------------

    public function test_notice_is_sent_to_every_device_with_signed_actions_and_no_sensitive_data(): void
    {
        $this->subscribe($this->mama, 'https://push.example/phone');
        $this->subscribe($this->mama, 'https://push.example/tablet');
        $this->subscribe($this->papa);
        $this->insistentRule();
        app(NotificationScheduler::class)->schedule(NotificationRule::first(), now());

        app(NotificationDispatcher::class)->dispatchDue();

        $this->assertCount(3, $this->push->sent);

        $message = json_decode($this->push->sent[0]['payload'], true);
        $this->assertSame('Amoxicilina', $message['title']);
        $this->assertSame('Toma de las 12:00', $message['body']);
        $this->assertTrue($message['requireInteraction']);
        $this->assertSame(['Confirmar', 'Posponer 10 min'], array_column($message['actions'], 'title'));
        $this->assertEqualsCanonicalizing(
            ['title', 'body', 'tag', 'requireInteraction', 'actions', 'data'],
            array_keys($message),
        );
        $this->assertStringStartsWith('/avisos/', $message['data']['ack_url']);
        $this->assertStringNotContainsString('Sofía', $this->push->sent[0]['payload']);
    }

    public function test_notice_follows_the_caretaker_language(): void
    {
        $this->papa->update(['language' => 'en']);
        $this->subscribe($this->papa);
        $this->insistentRule();
        app(NotificationScheduler::class)->schedule(NotificationRule::first(), now());

        app(NotificationDispatcher::class)->dispatchDue();

        $message = json_decode($this->push->sent[0]['payload'], true);
        $this->assertSame(['Confirm', 'Snooze 10 min'], array_column($message['actions'], 'title'));
    }

    public function test_caretaker_without_devices_counts_as_failed_delivery(): void
    {
        $this->insistentRule();
        $occurrence = app(NotificationScheduler::class)->schedule(NotificationRule::first(), now())->first();

        app(NotificationDispatcher::class)->dispatchDue();

        $this->assertCount(0, $this->push->sent);
        $this->assertSame(1, $occurrence->targets()->first()->failed_attempts);
        $this->assertDatabaseHas('notification_deliveries', ['channel' => 'push', 'status' => 'failed']);
    }

    public function test_expired_subscriptions_are_deleted_and_good_ones_still_deliver(): void
    {
        $this->subscribe($this->mama, 'https://push.example/gone');
        $this->subscribe($this->mama, 'https://push.example/ok');
        $this->push->expired = ['https://push.example/gone'];
        $this->insistentRule();
        app(NotificationScheduler::class)->schedule(NotificationRule::first(), now());

        app(NotificationDispatcher::class)->dispatchDue();

        $this->assertSame(['https://push.example/ok'], $this->mama->pushSubscriptions()->pluck('endpoint')->all());
        $this->assertDatabaseHas('notification_deliveries', ['channel' => 'push', 'status' => 'sent']);
    }

    public function test_a_device_that_keeps_failing_is_eventually_dropped(): void
    {
        $this->subscribe($this->mama, 'https://push.example/flaky');
        $this->push->failing = ['https://push.example/flaky'];
        config(['webpush.max_failures' => 2]);
        $rule = $this->insistentRule();
        $rule->recipients()->detach($this->papa->id);
        app(NotificationScheduler::class)->schedule($rule, now());

        foreach ([0, 5] as $minutes) {
            Carbon::setTestNow(Carbon::parse('2026-11-03 12:00:00')->addMinutes($minutes));
            app(NotificationDispatcher::class)->dispatchDue();
        }

        $this->assertSame(0, $this->mama->pushSubscriptions()->count());
    }

    // ---- Botones del aviso --------------------------------------------------

    public function test_ack_button_stops_the_notice_for_everyone_without_a_session(): void
    {
        $this->subscribe($this->mama, 'https://push.example/mama');
        $this->subscribe($this->papa, 'https://push.example/papa');
        $this->insistentRule();
        $occurrence = app(NotificationScheduler::class)->schedule(NotificationRule::first(), now())->first();
        app(NotificationDispatcher::class)->dispatchDue();

        $ackUrl = json_decode($this->push->sent[0]['payload'], true)['data']['ack_url'];
        $this->postJson($ackUrl)->assertOk();

        $this->assertSame(
            [TargetStatus::Acknowledged, TargetStatus::Acknowledged],
            $occurrence->targets()->get()->pluck('status')->all(),
        );

        $before = count($this->push->sent);
        Carbon::setTestNow(now()->addMinutes(10));
        app(NotificationDispatcher::class)->dispatchDue();
        $this->assertCount($before, $this->push->sent);
    }

    public function test_snooze_button_delays_the_notice(): void
    {
        $this->subscribe($this->mama);
        $this->insistentRule();
        $occurrence = app(NotificationScheduler::class)->schedule(NotificationRule::first(), now())->first();
        app(NotificationDispatcher::class)->dispatchDue();

        $snoozeUrl = json_decode($this->push->sent[0]['payload'], true)['data']['snooze_url'];
        $this->postJson($snoozeUrl)->assertOk();

        $this->assertEquals(now()->addMinutes(10), $occurrence->targets()->first()->next_send_at);
    }

    public function test_tampered_expired_or_unsigned_action_links_are_rejected(): void
    {
        $this->insistentRule();
        $occurrence = app(NotificationScheduler::class)->schedule(NotificationRule::first(), now())->first();
        $target = $occurrence->targets()->first();

        $signed = URL::temporarySignedRoute('reminders.act', now()->addHour(), ['target' => $target->id, 'action' => 'snooze', 'minutes' => 10], absolute: false);

        $this->postJson(str_replace('minutes=10', 'minutes=30', $signed))->assertForbidden();
        $this->postJson("/avisos/{$target->id}/accion?action=ack")->assertForbidden();

        Carbon::setTestNow(now()->addHours(2));
        $this->postJson($signed)->assertForbidden();

        $this->assertSame(TargetStatus::Pending, NotificationTarget::find($target->id)->status);
    }

    public function test_snooze_link_with_minutes_not_allowed_by_the_rule_is_refused(): void
    {
        $this->insistentRule();
        $target = app(NotificationScheduler::class)->schedule(NotificationRule::first(), now())->first()->targets()->first();

        $signed = URL::temporarySignedRoute('reminders.act', now()->addHour(), ['target' => $target->id, 'action' => 'snooze', 'minutes' => 7], absolute: false);

        $this->postJson($signed)->assertStatus(422);
    }

    public function test_vapid_command_prints_a_valid_key_pair(): void
    {
        $this->artisan('webpush:vapid')
            ->expectsOutputToContain('VAPID_PUBLIC_KEY=')
            ->expectsOutputToContain('VAPID_PRIVATE_KEY=')
            ->assertSuccessful();
    }
}
