<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Caretaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RendimientoTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00');
        $this->mama = app(SetupFamily::class)->handle([
            'family_name' => 'Ruiz', 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => 'Sofía', 'baby_birth_date' => '2026-06-01',
        ]);
        $this->actingAs($this->mama);
    }

    /** @return array<string, mixed> props de una visita de Inertia (XHR). */
    private function visit(string $url, array $headers = []): array
    {
        $version = (string) app(HandleInertiaRequests::class)->version(request());

        return $this->withHeaders($headers + ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => $version])
            ->get($url)->assertOk()->json();
    }

    public function test_translations_travel_on_the_first_load_and_are_skipped_once_the_browser_has_them(): void
    {
        $first = $this->visit('/mas');
        $this->assertArrayHasKey('t', $first['props']);
        $this->assertGreaterThan(20000, strlen(json_encode($first['props']['t'])));

        $next = $this->visit('/bitacora', ['X-Inertia-Except-Once-Props' => 't.es']);
        $this->assertArrayNotHasKey('t', $next['props']);
        $this->assertLessThan(8000, strlen(json_encode($next['props']))); // el resto de lo compartido es pequeño
    }

    public function test_changing_language_asks_for_the_translations_again(): void
    {
        $this->mama->update(['language' => 'en']);

        $page = $this->visit('/mas', ['X-Inertia-Except-Once-Props' => 't.es']); // el navegador solo tiene las de español

        $this->assertSame('Baby', $page['props']['t']['common']['baby']);
        $this->assertSame('t.en', array_key_first(array_filter($page['onceProps'], fn ($v) => ($v['prop'] ?? null) === 't')));
    }

    public function test_the_babies_are_queried_once_per_request_and_the_family_is_not_reloaded(): void
    {
        $this->visit('/'); // calienta

        DB::enableQueryLog();
        $this->visit('/');
        $sql = collect(DB::getQueryLog())->pluck('query');

        $this->assertSame(1, $sql->filter(fn ($q) => str_contains($q, 'from "babies"'))->count(), 'bebés consultados más de una vez');
        $this->assertLessThanOrEqual(1, $sql->filter(fn ($q) => str_contains($q, 'from "family_settings"'))->count(), 'ajustes consultados más de una vez');
        $this->assertLessThan(25, $sql->count());
    }

    public function test_the_active_baby_still_follows_the_session_and_stays_inside_the_family(): void
    {
        $family = $this->mama->family;
        $second = $family->babies()->create(['first_name' => 'Luis', 'birth_date' => '2026-09-01']);

        $this->withSession(['baby_id' => $second->id])->get('/')->assertInertia(fn ($page) => $page->where('activeBabyId', $second->id));
        $this->withSession(['baby_id' => 99999])->get('/')->assertInertia(fn ($page) => $page->where('activeBabyId', $family->babies()->orderBy('birth_date')->first()->id));

        $other = app(SetupFamily::class)->handle([
            'family_name' => 'Pérez', 'language' => 'es', 'admin_name' => 'Ana', 'pin' => '482915',
            'baby_first_name' => 'Otro', 'baby_birth_date' => '2026-06-01',
        ]);
        $this->actingAs($other)->withSession(['baby_id' => $second->id])->get('/')
            ->assertInertia(fn ($page) => $page->where('activeBabyId', $other->family->babies()->first()->id)); // nunca el de otra familia
    }

    public function test_the_service_worker_caches_only_hashed_build_assets(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("'/build/assets/'", $sw);
        $this->assertStringContainsString("request.method !== 'GET'", $sw);
        $this->assertStringContainsString('self.location.origin', $sw); // nada de otros orígenes
        $this->assertStringNotContainsString("caches.open('kidu-pages", $sw); // los datos nunca se guardan
    }

    public function test_the_production_template_avoids_database_sessions_and_the_deploy_script_optimizes(): void
    {
        $env = file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString('SESSION_DRIVER=file', $env);
        $this->assertStringContainsString('CACHE_STORE=file', $env);
        $this->assertStringContainsString('QUEUE_CONNECTION=sync', $env);
        $this->assertStringContainsString('php artisan optimize', file_get_contents(base_path('scripts/deploy.sh')));
    }
}
