<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Enums\DiaperType;
use App\Models\Caretaker;
use App\Models\DiaperLog;
use App\Models\SleepLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TemaNurseryTest extends TestCase
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

    private function owner(): array
    {
        $baby = $this->mama->family->babies()->first();

        return ['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'caretaker_id' => $this->mama->id];
    }

    public function test_pages_set_the_saved_theme_before_painting(): void
    {
        auth()->logout();
        $html = $this->get('/entrar')->getContent();

        $this->assertStringContainsString("localStorage.getItem('kidu-theme')", $html);
        $this->assertStringContainsString("document.documentElement.setAttribute('data-theme'", $html);
        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
    }

    public function test_nursery_page_shows_last_feed_diaper_and_open_sleep(): void
    {
        DiaperLog::create($this->owner() + ['occurred_at' => '2026-11-03 11:00:00', 'type' => DiaperType::Wet]);
        $sleep = SleepLog::create($this->owner() + ['started_at' => '2026-11-03 11:30:00', 'type' => 'nap']);

        $this->get('/nursery')->assertInertia(fn (Assert $page) => $page
            ->component('Nursery')
            ->where('baby.first_name', 'Sofía')
            ->where('lastDiaperAt', fn ($v) => str_starts_with($v, '2026-11-03T11:00'))
            ->where('lastFeedAt', null)
            ->where('sleepingId', $sleep->id)
            ->where('breastActive', false));
    }

    public function test_saving_from_nursery_returns_to_nursery_and_keeps_undo(): void
    {
        $this->post('/registrar/panal?nursery=1', ['occurred_at' => '2026-11-03T07:45', 'type' => 'wet'])
            ->assertRedirect('/nursery')->assertSessionHas('undo');

        $this->post('/registrar/panal', ['occurred_at' => '2026-11-03T07:46', 'type' => 'wet'])->assertRedirect('/');
    }

    public function test_waking_from_nursery_goes_back(): void
    {
        $sleep = SleepLog::create($this->owner() + ['started_at' => '2026-11-03 11:30:00', 'type' => 'nap']);

        $this->from('/nursery')->patch("/sueno/{$sleep->id}/despertar")->assertRedirect('/nursery');
        $this->assertNotNull($sleep->fresh()->ended_at);
    }

    public function test_nursery_requires_login(): void
    {
        auth()->logout();
        $this->get('/nursery')->assertRedirect();
    }
}
