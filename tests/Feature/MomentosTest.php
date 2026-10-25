<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Models\Caretaker;
use App\Models\LifeEvent;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExifJpeg;
use Tests\TestCase;

class MomentosTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00'); // 08:00 local (UTC-4)
        Storage::fake('local');
        $this->mama = $this->newFamily('Ruiz');
        $this->actingAs($this->mama);
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

    private function payload(array $o = []): array
    {
        return $o + ['type' => 'tooth', 'occurred_on' => '2026-11-02', 'title' => null, 'detail' => 'Incisivo central inferior', 'notes' => 'Con fiebrecita'];
    }

    public function test_creating_a_moment_with_photos_links_them_and_dates_them_to_the_day_of_the_event(): void
    {
        $this->post('/eventos', $this->payload(['photos' => [UploadedFile::fake()->image('d1.jpg', 400, 300), UploadedFile::fake()->image('d2.png', 400, 300)]]))
            ->assertSessionHasNoErrors();

        $event = LifeEvent::firstOrFail();
        $this->assertSame('tooth', $event->type->value);
        $this->assertSame('Incisivo central inferior', $event->detail);
        $this->assertSame($this->mama->id, $event->created_by);
        $this->assertSame(2, $event->photos()->count());
        $this->assertTrue($event->photos->first()->taken_at->equalTo('2026-11-02 12:00:00'));
        $this->assertSame($event->baby_id, $event->photos->first()->baby_id);
    }

    public function test_a_photo_that_carries_its_own_date_keeps_it_inside_a_moment(): void
    {
        $exif = UploadedFile::fake()->createWithContent('d.jpg', ExifJpeg::make(300, 200, ['date' => '2026:10:20 14:30:00']));

        $this->post('/eventos', $this->payload(['photos' => [$exif, UploadedFile::fake()->image('sin-exif.jpg', 300, 200)]]));

        $dates = Photo::orderBy('id')->get()->map(fn ($p) => $p->taken_at->format('Y-m-d H:i'))->all();
        $this->assertSame(['2026-10-20 18:30', '2026-11-02 12:00'], $dates); // la 1.ª conserva su EXIF; la 2.ª, el día del momento
    }

    public function test_validation(): void
    {
        $this->post('/eventos', $this->payload(['type' => 'volar']))->assertSessionHasErrors('type');
        $this->post('/eventos', $this->payload(['type' => 'other', 'title' => null]))->assertSessionHasErrors('title');
        $this->post('/eventos', $this->payload(['occurred_on' => '2026-11-10']))->assertSessionHasErrors('occurred_on');
        $this->post('/eventos', $this->payload(['occurred_on' => 'ayer']))->assertSessionHasErrors('occurred_on');
        $this->post('/eventos', $this->payload(['photos' => [UploadedFile::fake()->createWithContent('x.txt', 'hola')]]))->assertSessionHasErrors('photos.0');
        $this->assertSame(0, LifeEvent::count());

        $this->post('/eventos', $this->payload(['type' => 'other', 'title' => 'Primer cumplemés']))->assertSessionHasNoErrors();
        $this->post('/eventos', $this->payload(['occurred_on' => '2026-11-04']))->assertSessionHasNoErrors(); // margen de un día por la zona horaria
        $this->assertSame(2, LifeEvent::count());
    }

    public function test_list_and_detail_pages(): void
    {
        $this->post('/eventos', $this->payload(['photos' => [UploadedFile::fake()->image('d1.jpg', 400, 300)]]));
        $this->post('/eventos', $this->payload(['type' => 'food', 'occurred_on' => '2026-10-15', 'detail' => 'Aguacate']));
        $event = LifeEvent::where('type', 'tooth')->firstOrFail();

        $this->get('/eventos')->assertInertia(fn (Assert $page) => $page
            ->component('Eventos/Index')
            ->has('events', 2)
            ->where('events.0.type', 'tooth')
            ->where('events.0.photos.0.thumb_url', fn ($u) => str_contains($u, '/miniatura'))
            ->where('events.1.detail', 'Aguacate'));

        $this->get("/eventos/{$event->id}")->assertInertia(fn (Assert $page) => $page->component('Eventos/Show')->where('event.notes', 'Con fiebrecita')->has('event.photos', 1));
        $this->get("/eventos/{$event->id}/editar")->assertInertia(fn (Assert $page) => $page->component('Eventos/Form')->where('event.occurred_on', '2026-11-02')->has('types', 8));
        $this->get('/eventos/nuevo')->assertInertia(fn (Assert $page) => $page->where('today', '2026-11-03'));
    }

    public function test_editing_adding_photos_and_deleting_keeps_the_photos_in_the_gallery(): void
    {
        $this->post('/eventos', $this->payload());
        $event = LifeEvent::firstOrFail();

        $this->put("/eventos/{$event->id}", $this->payload(['detail' => 'Incisivo lateral', 'notes' => null]))->assertRedirect(route('events.show', $event));
        $this->assertSame('Incisivo lateral', $event->fresh()->detail);

        $this->post("/eventos/{$event->id}/fotos", ['photos' => [UploadedFile::fake()->image('d3.jpg', 300, 300)]])->assertSessionHasNoErrors();
        $this->assertSame(1, $event->photos()->count());
        $this->post("/eventos/{$event->id}/fotos", [])->assertSessionHasErrors('photos');

        $this->delete("/eventos/{$event->id}")->assertRedirect(route('events.index'));
        $this->assertSoftDeleted($event);
        $this->assertSame(1, Photo::count());
        $this->assertNull(Photo::first()->life_event_id);
    }

    public function test_a_family_cannot_touch_another_familys_moments(): void
    {
        $this->post('/eventos', $this->payload());
        $event = LifeEvent::firstOrFail();

        $this->actingAs($this->newFamily('Otra'));
        $this->get("/eventos/{$event->id}")->assertNotFound();
        $this->get("/eventos/{$event->id}/editar")->assertNotFound();
        $this->put("/eventos/{$event->id}", $this->payload())->assertNotFound();
        $this->delete("/eventos/{$event->id}")->assertNotFound();
        $this->post("/eventos/{$event->id}/fotos", ['photos' => [UploadedFile::fake()->image('x.jpg')]])->assertNotFound();
        $this->get('/eventos')->assertInertia(fn (Assert $page) => $page->has('events', 0));

        auth()->logout();
        $this->get('/eventos')->assertRedirect(route('login'));
    }
}
