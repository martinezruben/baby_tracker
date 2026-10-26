<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Models\Caretaker;
use App\Models\LifeEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MomentosComidaFiltroTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00');
        $this->mama = app(SetupFamily::class)->handle([
            'family_name' => 'Ruiz', 'language' => 'es', 'admin_name' => 'Mamá', 'pin' => '482915',
            'baby_first_name' => 'Matías', 'baby_birth_date' => '2026-03-01',
        ]);
        $this->actingAs($this->mama);
    }

    private function event(string $type, string $on, array $extra = []): LifeEvent
    {
        $baby = $this->mama->family->babies()->first();

        return LifeEvent::create($extra + ['family_id' => $baby->family_id, 'baby_id' => $baby->id, 'created_by' => $this->mama->id, 'type' => $type, 'occurred_on' => $on]);
    }

    public function test_a_new_food_keeps_seasonings_and_rating(): void
    {
        $this->post('/eventos', ['type' => 'food', 'occurred_on' => '2026-11-02', 'detail' => 'Pollo', 'seasonings' => 'Cilantro y orégano', 'rating' => 'liked'])->assertRedirect();

        $event = LifeEvent::firstOrFail();
        $this->assertSame('Cilantro y orégano', $event->seasonings);
        $this->assertSame('liked', $event->rating);

        $this->get("/eventos/{$event->id}")->assertInertia(fn (Assert $page) => $page->where('event.seasonings', 'Cilantro y orégano')->where('event.rating', 'liked'));
        $this->get("/eventos/{$event->id}/editar")->assertInertia(fn (Assert $page) => $page->where('event.seasonings', 'Cilantro y orégano')->where('event.rating', 'liked'));
    }

    public function test_seasonings_and_rating_can_be_left_empty_and_edited(): void
    {
        $this->post('/eventos', ['type' => 'food', 'occurred_on' => '2026-11-02', 'detail' => 'Papa'])->assertSessionHasNoErrors();
        $event = LifeEvent::firstOrFail();
        $this->assertNull($event->seasonings);
        $this->assertNull($event->rating);

        $this->put("/eventos/{$event->id}", ['type' => 'food', 'occurred_on' => '2026-11-02', 'detail' => 'Papa', 'seasonings' => 'Ajo', 'rating' => 'loved'])->assertRedirect();
        $this->assertSame('loved', $event->fresh()->rating);

        $this->put("/eventos/{$event->id}", ['type' => 'food', 'occurred_on' => '2026-11-02', 'detail' => 'Papa', 'seasonings' => '', 'rating' => ''])->assertRedirect();
        $this->assertNull($event->fresh()->seasonings);
        $this->assertNull($event->fresh()->rating);
    }

    public function test_they_are_ignored_for_other_types_and_validated(): void
    {
        $this->post('/eventos', ['type' => 'tooth', 'occurred_on' => '2026-11-02', 'seasonings' => 'Ajo', 'rating' => 'liked'])->assertSessionHasNoErrors();
        $this->assertNull(LifeEvent::firstOrFail()->seasonings);
        $this->assertNull(LifeEvent::firstOrFail()->rating);

        $this->post('/eventos', ['type' => 'food', 'occurred_on' => '2026-11-02', 'rating' => 'meh'])->assertSessionHasErrors('rating');
        $this->post('/eventos', ['type' => 'food', 'occurred_on' => '2026-11-02', 'seasonings' => str_repeat('a', 201)])->assertSessionHasErrors('seasonings');
    }

    public function test_the_list_can_be_filtered_by_type_and_reports_counts(): void
    {
        $this->event('food', '2026-10-25', ['detail' => 'Papa', 'seasonings' => 'Ajo', 'rating' => 'liked']);
        $this->event('food', '2026-10-21', ['detail' => 'Banana']);
        $this->event('tooth', '2026-10-20');
        $this->event('trip', '2026-10-10');

        $this->get('/eventos')->assertInertia(fn (Assert $page) => $page
            ->has('events', 4)->where('tipo', null)->where('counts.food', 2)->where('counts.tooth', 1)->where('counts.trip', 1)->missing('counts.word'));

        $this->get('/eventos?tipo=food')->assertInertia(fn (Assert $page) => $page
            ->has('events', 2)->where('tipo', 'food')->where('events.0.detail', 'Papa')->where('events.0.seasonings', 'Ajo')->where('events.0.rating', 'liked')
            ->where('counts.tooth', 1)); // los conteos no dependen del filtro

        $this->get('/eventos?tipo=tooth')->assertInertia(fn (Assert $page) => $page->has('events', 1));
        $this->get('/eventos?tipo=inventado')->assertInertia(fn (Assert $page) => $page->has('events', 4)->where('tipo', null));
    }

    public function test_the_filter_stays_inside_the_active_baby_and_family(): void
    {
        $this->event('food', '2026-10-25', ['detail' => 'Papa']);

        $other = app(SetupFamily::class)->handle([
            'family_name' => 'Pérez', 'language' => 'es', 'admin_name' => 'Ana', 'pin' => '482915',
            'baby_first_name' => 'Luis', 'baby_birth_date' => '2026-03-01',
        ]);
        $this->actingAs($other);

        $this->get('/eventos?tipo=food')->assertInertia(fn (Assert $page) => $page->has('events', 0)->where('counts', []));
    }
}
