<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\OccurrenceStatus;
use App\Enums\TargetStatus;
use App\Models\Caretaker;
use App\Models\NotificationRule;
use App\Models\NotificationTarget;
use App\Models\PushSubscription;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\Push\PushClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePushClient;
use Tests\TestCase;

class RecordatoriosPantallaTest extends TestCase
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

        $this->mama = $this->newFamily('Ruiz', 'Mamá');
        $this->papa = $this->mama->family->caretakers()->create(['login_id' => 'papa', 'name' => 'Papá', 'pin' => '246802']);
    }

    private function newFamily(string $name, string $admin): Caretaker
    {
        $caretaker = app(SetupFamily::class)->handle([
            'family_name' => $name, 'language' => 'es', 'admin_name' => $admin, 'pin' => '482915',
            'baby_first_name' => 'Sofía', 'baby_birth_date' => '2026-06-01',
        ]);
        $caretaker->family->settings->update(['timezone' => 'America/Santo_Domingo']); // UTC-4

        return $caretaker;
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Dar amoxicilina',
            'body' => 'Con comida',
            'event_at' => '2026-11-04T09:00',
            'offsets_minutes' => [0, -60],
            'repeat_every_minutes' => null,
            'max_repeats' => null,
            'requires_ack' => false,
            'ack_scope' => 'any',
            'snooze_options' => [5, 10, 15, 30],
            'quiet_start' => null,
            'quiet_end' => null,
            'respect_quiet_hours' => true,
            'recipients' => [$this->mama->id, $this->papa->id],
        ];
    }

    public function test_creating_a_reminder_schedules_one_notice_per_moment_and_recipient(): void
    {
        $this->actingAs($this->mama)->post('/recordatorios', $this->payload())
            ->assertRedirect(route('reminders.index'));

        $rule = NotificationRule::firstOrFail();
        // 09:00 en Santo Domingo (UTC-4) = 13:00 UTC
        $this->assertTrue($rule->event_at->equalTo('2026-11-04 13:00:00'));
        $this->assertSame([-60, 0], $rule->offsets_minutes);
        $this->assertSame(['push'], $rule->channels);
        $this->assertDatabaseCount('notification_occurrences', 2);
        $this->assertDatabaseCount('notification_targets', 4);
        $this->assertSame(
            ['2026-11-04 12:00:00', '2026-11-04 12:00:00', '2026-11-04 13:00:00', '2026-11-04 13:00:00'],
            NotificationTarget::orderBy('next_send_at')->pluck('next_send_at')->map->format('Y-m-d H:i:s')->all(),
        );
    }

    public function test_insistent_reminder_needs_an_interval_and_repeating_needs_a_limit(): void
    {
        $this->actingAs($this->mama)
            ->post('/recordatorios', $this->payload(['requires_ack' => true]))
            ->assertSessionHasErrors('repeat_every_minutes');

        $this->post('/recordatorios', $this->payload(['repeat_every_minutes' => 10]))
            ->assertSessionHasErrors('max_repeats');

        $this->post('/recordatorios', $this->payload(['requires_ack' => true, 'repeat_every_minutes' => 5, 'max_repeats' => 9]))
            ->assertSessionHasNoErrors();
        $this->assertNull(NotificationRule::first()->max_repeats); // insistir = sin límite propio
        $this->assertTrue(NotificationRule::first()->requires_ack);
    }

    public function test_validation_rejects_past_dates_foreign_recipients_and_bad_options(): void
    {
        $stranger = $this->newFamily('Otra', 'Ajena');

        $this->actingAs($this->mama)->post('/recordatorios', $this->payload(['event_at' => '2026-11-02T09:00']))
            ->assertSessionHasErrors('event_at');
        $this->post('/recordatorios', $this->payload(['recipients' => [$stranger->id]]))
            ->assertSessionHasErrors('recipients.0');
        $this->post('/recordatorios', $this->payload(['recipients' => []]))
            ->assertSessionHasErrors('recipients');
        $this->post('/recordatorios', $this->payload(['offsets_minutes' => [-7]]))
            ->assertSessionHasErrors('offsets_minutes.0');
        $this->post('/recordatorios', $this->payload(['snooze_options' => [3]]))
            ->assertSessionHasErrors('snooze_options.0');
        $this->post('/recordatorios', $this->payload(['quiet_start' => '22:00']))
            ->assertSessionHasErrors('quiet_end');

        $this->assertDatabaseCount('notification_rules', 0);
    }

    public function test_future_check_uses_the_family_timezone_not_the_servers(): void
    {
        // Ahora: 2026-11-03 12:00 UTC = 08:00 en Santo Domingo.
        $this->actingAs($this->mama)
            ->post('/recordatorios', $this->payload(['event_at' => '2026-11-03T10:00']))
            ->assertSessionHasNoErrors(); // 10:00 local = 14:00 UTC: es futuro

        $this->post('/recordatorios', $this->payload(['event_at' => '2026-11-03T07:00']))
            ->assertSessionHasErrors('event_at'); // 07:00 local = 11:00 UTC: ya pasó
    }

    public function test_editing_reschedules_even_when_the_time_does_not_change(): void
    {
        $this->actingAs($this->mama)->post('/recordatorios', $this->payload());
        $rule = NotificationRule::firstOrFail();

        $this->put("/recordatorios/{$rule->id}", $this->payload(['recipients' => [$this->papa->id]]))
            ->assertRedirect(route('reminders.index'));

        $targets = NotificationTarget::where('status', TargetStatus::Pending)->get();
        $this->assertCount(2, $targets); // 2 momentos × 1 destinatario
        $this->assertSame([$this->papa->id], $targets->pluck('caretaker_id')->unique()->values()->all());

        $this->put("/recordatorios/{$rule->id}", $this->payload(['event_at' => '2026-11-05T09:00']));
        $this->assertTrue($rule->fresh()->event_at->equalTo('2026-11-05 13:00:00'));
        // 2 momentos × 2 destinatarios, y nada queda programado para la fecha anterior
        $this->assertSame(4, NotificationTarget::where('status', TargetStatus::Pending)->count());
        $this->assertSame(0, NotificationTarget::where('status', TargetStatus::Pending)->where('next_send_at', '<', '2026-11-05')->count());
    }

    public function test_pausing_cancels_and_resuming_reschedules(): void
    {
        $this->actingAs($this->mama)->post('/recordatorios', $this->payload());
        $rule = NotificationRule::firstOrFail();

        $this->patch("/recordatorios/{$rule->id}/activar");
        $this->assertFalse($rule->fresh()->is_active);
        $this->assertSame(0, NotificationTarget::where('status', TargetStatus::Pending)->count());

        $this->patch("/recordatorios/{$rule->id}/activar");
        $this->assertTrue($rule->fresh()->is_active);
        $this->assertSame(4, NotificationTarget::where('status', TargetStatus::Pending)->count());
    }

    public function test_deleting_cancels_the_notices_and_nothing_is_sent(): void
    {
        $this->actingAs($this->mama)->post('/recordatorios', $this->payload());
        $rule = NotificationRule::firstOrFail();

        $this->delete("/recordatorios/{$rule->id}")->assertRedirect(route('reminders.index'));

        $this->assertSoftDeleted($rule);
        $this->assertSame(OccurrenceStatus::Cancelled, $rule->occurrences()->first()->status);

        Carbon::setTestNow('2026-11-05 00:00:00');
        $this->assertSame(0, app(NotificationDispatcher::class)->dispatchDue());
    }

    public function test_a_family_cannot_see_or_change_another_familys_reminders(): void
    {
        $this->actingAs($this->mama)->post('/recordatorios', $this->payload());
        $rule = NotificationRule::firstOrFail();
        $stranger = $this->newFamily('Otra', 'Ajena');

        $this->actingAs($stranger)->get("/recordatorios/{$rule->id}/editar")->assertNotFound();
        $this->put("/recordatorios/{$rule->id}", $this->payload(['recipients' => [$stranger->id]]))->assertNotFound();
        $this->patch("/recordatorios/{$rule->id}/activar")->assertNotFound();
        $this->delete("/recordatorios/{$rule->id}")->assertNotFound();

        $this->get('/recordatorios')->assertInertia(fn (Assert $page) => $page->has('reminders', 0));
    }

    public function test_index_and_form_pages_expose_what_the_screens_need(): void
    {
        $this->actingAs($this->mama)->post('/recordatorios', $this->payload(['requires_ack' => true, 'repeat_every_minutes' => 5]));
        $rule = NotificationRule::firstOrFail();

        $this->get('/recordatorios')->assertInertia(fn (Assert $page) => $page
            ->component('Recordatorios/Index')
            ->has('reminders', 1)
            ->where('reminders.0.state', 'upcoming')
            ->where('reminders.0.event_at', '2026-11-04T09:00')
            ->where('reminders.0.recipients', ['Mamá', 'Papá']));

        $this->get('/recordatorios/nuevo')->assertInertia(fn (Assert $page) => $page
            ->component('Recordatorios/Form')
            ->has('caretakers', 2)
            ->where('defaults.recipient', $this->mama->id)
            ->where('options.snooze', [5, 10, 15, 30, 60, 120]));

        $this->get("/recordatorios/{$rule->id}/editar")->assertInertia(fn (Assert $page) => $page
            ->component('Recordatorios/Form')
            ->where('reminder.title', 'Dar amoxicilina')
            ->where('reminder.requires_ack', true)
            ->where('reminder.offsets_minutes', [-60, 0]));
    }

    public function test_the_reminder_reaches_the_phone_and_stops_after_confirming(): void
    {
        PushSubscription::create([
            'caretaker_id' => $this->mama->id, 'endpoint' => 'https://push.example/m',
            'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example/m'), 'p256dh' => 'k', 'auth' => 'a',
        ]);
        $this->actingAs($this->mama)->post('/recordatorios', $this->payload([
            'recipients' => [$this->mama->id], 'offsets_minutes' => [0], 'requires_ack' => true, 'repeat_every_minutes' => 5,
        ]));

        Carbon::setTestNow('2026-11-04 13:00:00');
        app(NotificationDispatcher::class)->dispatchDue();
        $this->assertCount(1, $this->push->sent);

        $ack = json_decode($this->push->sent[0]['payload'], true)['data']['ack_url'];
        $this->postJson($ack)->assertOk();

        Carbon::setTestNow('2026-11-04 13:10:00');
        app(NotificationDispatcher::class)->dispatchDue();
        $this->assertCount(1, $this->push->sent);
    }

    public function test_test_alert_reaches_the_current_caretakers_devices(): void
    {
        $this->actingAs($this->mama)->postJson('/push/prueba')->assertStatus(422);

        PushSubscription::create([
            'caretaker_id' => $this->mama->id, 'endpoint' => 'https://push.example/m',
            'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example/m'), 'p256dh' => 'k', 'auth' => 'a',
        ]);

        $this->actingAs($this->mama->refresh())->postJson('/push/prueba')->assertOk();

        $message = json_decode($this->push->sent[0]['payload'], true);
        $this->assertSame('Aviso de prueba', $message['title']);
        $this->assertSame([], $message['actions']);
    }
}
