<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\TargetStatus;
use App\Models\Caretaker;
use App\Models\NotificationRule;
use App\Models\NotificationTarget;
use App\Models\PushSubscription;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\Push\MinishlinkPushClient;
use App\Services\Notifications\Push\PushClient;
use App\Support\PushDiagnostics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Minishlink\WebPush\VAPID;
use Tests\Support\FakePushClient;
use Tests\TestCase;

class PushPruebasTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    private Caretaker $papa;

    private FakePushClient $push;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:30');
        Cache::flush();
        $this->push = new FakePushClient;
        $this->app->instance(PushClient::class, $this->push);

        $this->mama = app(SetupFamily::class)->handle([
            'family_name' => 'Ruiz', 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => 'Sofía', 'baby_birth_date' => '2026-06-01',
        ]);
        $this->papa = $this->mama->family->caretakers()->create(['login_id' => 'papa', 'name' => 'Papá', 'pin' => '246802', 'role' => 'user']);
    }

    private function subscribe(Caretaker $who, string $endpoint): void
    {
        PushSubscription::create(['caretaker_id' => $who->id, 'endpoint' => $endpoint, 'endpoint_hash' => PushSubscription::hashEndpoint($endpoint), 'p256dh' => 'k', 'auth' => 'a']);
    }

    public function test_scheduled_test_is_a_real_reminder_for_the_caller_only_due_in_a_minute(): void
    {
        $this->subscribe($this->mama, 'https://push.example/mama');
        $this->subscribe($this->papa, 'https://push.example/papa');
        $this->actingAs($this->mama);

        $this->postJson('/push/prueba-programada')->assertOk()->assertJsonStructure(['message']);

        $rule = NotificationRule::firstOrFail();
        $this->assertTrue($rule->requires_ack);
        $this->assertSame('2026-11-03 12:01:00', $rule->event_at->toDateTimeString());
        $this->assertSame([$this->mama->id], $rule->recipients()->pluck('caretakers.id')->all());

        // Aún no vence: no sale nada. Un minuto después, el despachador lo envía solo a quien lo pidió.
        $this->assertSame(0, app(NotificationDispatcher::class)->dispatchDue());
        Carbon::setTestNow('2026-11-03 12:01:05');
        $this->assertSame(1, app(NotificationDispatcher::class)->dispatchDue());

        $this->assertCount(1, $this->push->sent);
        $this->assertSame('https://push.example/mama', $this->push->sent[0]['endpoint']);
        $payload = json_decode($this->push->sent[0]['payload'], true);
        $this->assertTrue($payload['requireInteraction']);
        $this->assertSame(['ack', 'snooze'], array_column($payload['actions'], 'action'));
    }

    public function test_the_notice_keeps_insisting_until_confirmed(): void
    {
        $this->subscribe($this->mama, 'https://push.example/mama');
        $this->actingAs($this->mama)->postJson('/push/prueba-programada');

        Carbon::setTestNow('2026-11-03 12:01:05');
        app(NotificationDispatcher::class)->dispatchDue();
        Carbon::setTestNow('2026-11-03 12:06:10');
        app(NotificationDispatcher::class)->dispatchDue();

        $this->assertCount(2, $this->push->sent);
        $this->assertSame(TargetStatus::Pending, NotificationTarget::firstOrFail()->status);
    }

    public function test_immediate_test_shows_the_technical_reason_only_to_admins(): void
    {
        $this->subscribe($this->mama, 'https://push.example/mama');
        $this->subscribe($this->papa, 'https://push.example/papa');
        $this->push->failing = ['https://push.example/mama', 'https://push.example/papa'];

        $this->actingAs($this->mama)->postJson('/push/prueba')->assertStatus(422)->assertJsonPath('detail', fn ($v) => str_contains($v, 'Server error'));
        $this->actingAs($this->papa)->postJson('/push/prueba')->assertStatus(422)->assertJsonPath('detail', null);
    }

    public function test_dispatch_command_leaves_a_heartbeat_and_status_reports_it(): void
    {
        $this->actingAs($this->mama);
        $this->getJson('/push/estado')->assertOk()->assertJsonPath('dispatcher_ok', false)->assertJsonPath('dispatcher_last_run', null);

        $this->artisan('reminders:dispatch')->assertSuccessful();

        $this->getJson('/push/estado')->assertOk()->assertJsonPath('dispatcher_ok', true)->assertJsonPath('devices_mine', 0);

        Carbon::setTestNow('2026-11-03 12:10:00'); // el cron lleva 10 minutos sin correr
        $this->getJson('/push/estado')->assertJsonPath('dispatcher_ok', false);
    }

    public function test_status_counts_overdue_notices_and_my_devices(): void
    {
        $this->subscribe($this->mama, 'https://push.example/mama');
        $this->subscribe($this->papa, 'https://push.example/papa');
        $this->actingAs($this->mama)->postJson('/push/prueba-programada');

        Carbon::setTestNow('2026-11-03 12:10:00'); // venció hace 9 min y nadie lo despachó

        $this->getJson('/push/estado')->assertJsonPath('overdue', 1)->assertJsonPath('devices_mine', 1)->assertJsonPath('devices_total', 2);
    }

    public function test_status_is_for_admins_only(): void
    {
        $this->actingAs($this->papa)->getJson('/push/estado')->assertForbidden();
        auth()->logout();
        $this->getJson('/push/estado')->assertUnauthorized();
    }

    public function test_vapid_public_key_validation(): void
    {
        $diagnostics = new PushDiagnostics;
        $valid = rtrim(strtr(base64_encode("\x04".random_bytes(64)), '+/', '-_'), '=');

        $this->assertTrue($diagnostics->validPublicKey($valid));
        $this->assertFalse($diagnostics->validPublicKey(''));
        $this->assertFalse($diagnostics->validPublicKey('no-es-una-clave'));
        $this->assertFalse($diagnostics->validPublicKey(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=')));
    }

    public function test_check_command_reports_problems_and_sends_a_test(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null, 'app.url' => 'http://localhost']);

        $this->artisan('webpush:check')->expectsOutputToContain('Claves VAPID')->assertFailed();

        $keys = VAPID::createVapidKeys();
        config(['webpush.vapid.public_key' => $keys['publicKey'], 'webpush.vapid.private_key' => $keys['privateKey'], 'app.url' => 'https://kidu.example']);
        app(PushDiagnostics::class)->beat();
        $this->subscribe($this->mama, 'https://push.example/mama');

        $this->artisan('webpush:check', ['--send' => 'Mamá'])->expectsOutputToContain('Aviso de prueba enviado')->assertSuccessful();
        $this->assertCount(1, $this->push->sent);

        $this->artisan('webpush:check', ['--send' => 'Nadie'])->expectsOutputToContain('No existe un cuidador')->assertFailed();
    }

    public function test_real_client_does_not_turn_the_gmp_recommendation_into_an_exception(): void
    {
        $logger = (new \ReflectionMethod(MinishlinkPushClient::class, 'logger'))->invoke(new MinishlinkPushClient);

        // La librería avisa con un notice si falta GMP/BCMath; sin este filtro Laravel lo vuelve ErrorException.
        $logger->notice('It is highly recommended to install the GMP or BCMath extension');
        $logger->info('x');

        $this->addToAssertionCount(1);
    }
}
