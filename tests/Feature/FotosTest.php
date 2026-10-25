<?php

namespace Tests\Feature;

use App\Actions\SetupFamily;
use App\Models\Caretaker;
use App\Models\Photo;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExifJpeg;
use Tests\TestCase;

class FotosTest extends TestCase
{
    use RefreshDatabase;

    private Caretaker $mama;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00'); // 08:00 en Santo Domingo (UTC-4)
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

    private function jpeg(array $exif = [], int $w = 800, int $h = 600, string $name = 'foto.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, ExifJpeg::make($w, $h, $exif));
    }

    private function upload(array $files, array $extra = [])
    {
        return $this->post('/fotos', ['photos' => $files] + $extra);
    }

    // ---- Subir ----------------------------------------------------------------------

    public function test_exif_date_and_gps_become_editable_columns_and_orientation_is_applied(): void
    {
        $this->upload([$this->jpeg(['orientation' => 6, 'date' => '2026:10:20 14:30:00'], 800, 600)])->assertSessionHasNoErrors();

        $photo = Photo::firstOrFail();
        // 14:30 hora de la familia (UTC-4) = 18:30 UTC
        $this->assertTrue($photo->taken_at->equalTo('2026-10-20 18:30:00'));
        $this->assertEqualsWithDelta(18.475, $photo->latitude, 0.0001);   // 18°28'30" N
        $this->assertEqualsWithDelta(-69.89, $photo->longitude, 0.0001);  // 69°53'24" W
        // Orientación 6 = girar 90°: la foto de 800x600 queda de 600x800.
        $this->assertSame([600, 800], [$photo->width, $photo->height]);
        $this->assertSame($this->mama->id, $photo->uploaded_by);
        $this->assertNull($photo->place_name);
    }

    public function test_the_stored_copy_has_no_metadata_and_there_is_a_thumbnail(): void
    {
        $this->upload([$this->jpeg(['orientation' => 1], 3000, 2000)]);
        $photo = Photo::firstOrFail();

        $disk = Storage::disk('local');
        $disk->assertExists([$photo->path, $photo->thumb_path]);
        $this->assertStringStartsWith("photos/{$this->mama->family_id}/", $photo->path);

        // La copia guardada no conserva el GPS de la cámara.
        $exif = @exif_read_data($disk->path($photo->path), 'ANY_TAG', true);
        $this->assertArrayNotHasKey('GPS', is_array($exif) ? $exif : []);

        [$w, $h] = getimagesizefromstring($disk->get($photo->path));
        $this->assertSame(2048, max($w, $h)); // reducida al máximo
        [$tw, $th] = getimagesizefromstring($disk->get($photo->thumb_path));
        $this->assertSame(480, max($tw, $th));
        $this->assertSame($photo->size, $disk->size($photo->path) + $disk->size($photo->thumb_path));
    }

    public function test_photos_without_exif_use_the_upload_time_and_pngs_and_webps_are_accepted(): void
    {
        $this->upload([UploadedFile::fake()->image('a.png', 300, 200), UploadedFile::fake()->image('b.webp', 300, 200)])
            ->assertSessionHasNoErrors();

        $this->assertSame(['image/png', 'image/webp'], Photo::orderBy('id')->pluck('mime')->all());
        $this->assertTrue(Photo::first()->taken_at->equalTo('2026-11-03 12:00:00'));
        $this->assertNull(Photo::first()->latitude);
    }

    public function test_only_real_images_are_accepted(): void
    {
        // Un archivo que se hace pasar por .jpg lo rechaza la comprobación del contenido.
        $this->upload([UploadedFile::fake()->createWithContent('malo.jpg', '<?php echo 1;')])->assertSessionHasErrors('photos');
        $this->upload([UploadedFile::fake()->createWithContent('nota.txt', 'hola')])->assertSessionHasErrors('photos.0');
        $this->upload([UploadedFile::fake()->create('foto.gif', 10, 'image/gif')])->assertSessionHasErrors('photos.0');
        $this->upload([UploadedFile::fake()->image('enorme.png')->size(15361)])->assertSessionHasErrors('photos.0');
        $this->upload([])->assertSessionHasErrors('photos');
        $this->post('/fotos', [])->assertSessionHasErrors('photos');
        $this->assertSame(0, Photo::count());
    }

    public function test_the_family_photo_quota_is_enforced(): void
    {
        $this->mama->family->settings->update(['photo_quota_mb' => 0]);

        $this->upload([$this->jpeg()])->assertSessionHasErrors('photos');

        $this->assertSame(0, Photo::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_tags_are_normalized_shared_and_reused(): void
    {
        $this->upload([$this->jpeg(), $this->jpeg()], ['tags' => ['  Abuela ', 'abuela', 'Playa', '']]);

        $this->assertSame(['abuela', 'playa'], Tag::orderBy('name')->pluck('name')->all());
        foreach (Photo::all() as $photo) {
            $this->assertEqualsCanonicalizing(['abuela', 'playa'], $photo->tags->pluck('name')->all());
        }
    }

    // ---- Galería ------------------------------------------------------------------------

    private function makePhoto(string $takenAt, array $attrs = []): Photo
    {
        $baby = $this->mama->family->babies()->first();

        return Photo::create($attrs + [
            'family_id' => $this->mama->family_id, 'baby_id' => $baby->id, 'uploaded_by' => $this->mama->id,
            'path' => 'photos/x.jpg', 'thumb_path' => 'photos/x_thumb.jpg', 'original_name' => 'x.jpg', 'mime' => 'image/jpeg',
            'size' => 1000, 'width' => 100, 'height' => 100, 'taken_at' => $takenAt,
        ]);
    }

    public function test_gallery_filters_by_tag_favorites_month_and_place(): void
    {
        $beach = $this->makePhoto('2026-10-20 15:00:00', ['place_name' => 'Playa Boca Chica', 'is_favorite' => true]);
        $this->makePhoto('2026-09-05 15:00:00', ['place_name' => 'Casa de la abuela']);
        $this->makePhoto('2026-10-31 02:00:00');  // 30 oct 22:00 local: sigue siendo octubre
        $this->makePhoto('2026-11-01 02:00:00');  // 31 oct 22:00 local: octubre
        $this->makePhoto('2026-11-01 04:30:00');  // 1 nov 00:30 local: noviembre
        $tag = Tag::create(['family_id' => $this->mama->family_id, 'name' => 'playa']);
        $beach->tags()->attach($tag);

        $count = fn (string $q) => count($this->get('/fotos'.$q)->viewData('page')['props']['photos']);

        $this->assertSame(5, $count(''));
        $this->assertSame(1, $count("?etiqueta={$tag->id}"));
        $this->assertSame(1, $count('?favoritas=1'));
        $this->assertSame(3, $count('?mes=2026-10'));
        $this->assertSame(1, $count('?mes=2026-09'));
        $this->assertSame(1, $count('?lugar=abuela'));
        $this->assertSame(1, $count('?lugar=BOCA'));
        $this->assertSame(0, $count('?lugar=%25')); // el comodín no se interpreta
        $this->assertSame(1, $count("?etiqueta={$tag->id}&mes=2026-10&favoritas=1&lugar=playa"));

        $this->get('/fotos')->assertInertia(fn (Assert $page) => $page
            ->component('Fotos/Index')
            ->where('months', ['2026-11', '2026-10', '2026-09'])
            ->where('tags', [['id' => $tag->id, 'name' => 'playa', 'count' => 1]])
            ->where('quota.total_mb', 2048));
    }

    public function test_cursor_pagination_has_no_gaps_or_repeats_even_with_equal_timestamps(): void
    {
        foreach (range(1, 60) as $i) {
            $this->makePhoto($i <= 20 ? '2026-10-01 10:00:00' : Carbon::parse('2026-10-01 10:00:00')->addMinutes($i)->toDateTimeString());
        }

        $seen = [];
        $cursor = null;
        $pages = 0;
        do {
            $props = $this->get('/fotos'.($cursor ? '?antes='.urlencode($cursor) : ''))->viewData('page')['props'];
            array_push($seen, ...array_column($props['photos'], 'id'));
            $cursor = $props['next'];
            $pages++;
        } while ($cursor && $pages < 5);

        $this->assertSame(60, count($seen));
        $this->assertSame(60, count(array_unique($seen)));
        $this->assertSame(2, $pages);
    }

    // ---- Detalle y edición --------------------------------------------------------------------

    public function test_detail_shows_neighbours_and_editing_updates_caption_date_place_and_tags(): void
    {
        $old = $this->makePhoto('2026-10-01 10:00:00');
        $mid = $this->makePhoto('2026-10-10 10:00:00');
        $new = $this->makePhoto('2026-10-20 10:00:00');

        $this->get("/fotos/{$mid->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Fotos/Show')
            ->where('newer_id', $new->id)
            ->where('older_id', $old->id)
            ->where('photo.taken_local', '2026-10-10T06:00'));

        $this->patch("/fotos/{$mid->id}", [
            'caption' => 'Primer baño en el mar', 'taken_at' => '2026-10-11T09:15', 'place_name' => 'Boca Chica', 'tags' => ['Playa', 'primera vez'],
        ])->assertSessionHasNoErrors();

        $mid->refresh();
        $this->assertSame('Primer baño en el mar', $mid->caption);
        $this->assertTrue($mid->taken_at->equalTo('2026-10-11 13:15:00'));
        $this->assertSame('Boca Chica', $mid->place_name);
        $this->assertEqualsCanonicalizing(['playa', 'primera vez'], $mid->tags->pluck('name')->all());

        $this->patch("/fotos/{$mid->id}", ['taken_at' => '2026-10-11T09:15', 'tags' => []]);
        $this->assertSame(0, $mid->fresh()->tags()->count());
        $this->patch("/fotos/{$mid->id}", ['taken_at' => 'ayer'])->assertSessionHasErrors('taken_at');
    }

    public function test_favorite_toggle_and_hide_location_with_one_tap(): void
    {
        $photo = $this->makePhoto('2026-10-10 10:00:00', ['latitude' => 18.47, 'longitude' => -69.89, 'place_name' => 'Casa']);

        $this->post("/fotos/{$photo->id}/favorita");
        $this->assertTrue($photo->fresh()->is_favorite);
        $this->post("/fotos/{$photo->id}/favorita");
        $this->assertFalse($photo->fresh()->is_favorite);

        $this->delete("/fotos/{$photo->id}/ubicacion")->assertRedirect();
        $photo->refresh();
        $this->assertNull($photo->latitude);
        $this->assertNull($photo->longitude);
        $this->assertNull($photo->place_name);
        $this->assertFalse($photo->hasLocation());
    }

    public function test_deleting_removes_the_files_and_frees_the_quota(): void
    {
        $this->upload([$this->jpeg()]);
        $photo = Photo::firstOrFail();
        Storage::disk('local')->assertExists($photo->path);

        $this->delete("/fotos/{$photo->id}")->assertRedirect(route('photos.index'));

        $this->assertSame(0, Photo::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ---- Archivos privados ------------------------------------------------------------------------

    public function test_files_are_served_only_to_logged_in_members_of_the_family(): void
    {
        $this->upload([$this->jpeg()]);
        $photo = Photo::firstOrFail();

        $full = $this->get("/fotos/{$photo->id}/archivo")->assertOk();
        $full->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('image/jpeg', $full->headers->get('Content-Type'));
        $this->get("/fotos/{$photo->id}/miniatura")->assertOk();

        $this->actingAs($this->newFamily('Otra'));
        foreach (['archivo', 'miniatura', ''] as $suffix) {
            $this->get("/fotos/{$photo->id}/".$suffix)->assertNotFound();
        }
        $this->patch("/fotos/{$photo->id}", ['taken_at' => '2026-10-11T09:15'])->assertNotFound();
        $this->post("/fotos/{$photo->id}/favorita")->assertNotFound();
        $this->delete("/fotos/{$photo->id}/ubicacion")->assertNotFound();
        $this->delete("/fotos/{$photo->id}")->assertNotFound();
        $this->get('/fotos')->assertInertia(fn (Assert $page) => $page->has('photos', 0));

        auth()->logout();
        $this->get("/fotos/{$photo->id}/archivo")->assertRedirect(route('login'));
    }
}
