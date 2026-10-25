<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\Tag;
use App\Support\ActiveBaby;
use App\Support\FamilyClock;
use App\Support\PhotoStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoController extends Controller
{
    public const PAGE = 48;

    public function index(Request $request, PhotoStorage $storage): Response
    {
        $baby = ActiveBaby::for($request);
        $family = $request->user()->family;
        $clock = FamilyClock::for($family);

        $filters = [
            'etiqueta' => $request->integer('etiqueta') ?: null,
            'favoritas' => $request->boolean('favoritas'),
            'mes' => preg_match('/^\d{4}-\d{2}$/', (string) $request->query('mes')) ? $request->query('mes') : null,
            'lugar' => trim((string) $request->query('lugar')) ?: null,
        ];

        $query = Photo::query()->where('baby_id', $baby->id)->with('tags:id,name');

        if ($filters['etiqueta']) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $filters['etiqueta'])->where('tags.family_id', $family->id));
        }
        if ($filters['favoritas']) {
            $query->where('is_favorite', true);
        }
        if ($filters['mes']) {
            $start = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $filters['mes'].'-01 00:00:00', $clock->timezone);
            $query->where('taken_at', '>=', $start->utc())->where('taken_at', '<', $start->addMonth()->utc());
        }
        if ($filters['lugar']) {
            $query->where('place_name', 'like', '%'.addcslashes($filters['lugar'], '%_\\').'%');
        }

        if ($cursor = $this->parseCursor($request->query('antes'))) {
            [$at, $id] = $cursor;
            $query->where(fn ($q) => $q->where('taken_at', '<', $at)->orWhere(fn ($q) => $q->where('taken_at', $at)->where('id', '<', $id)));
        }

        $rows = $query->orderByDesc('taken_at')->orderByDesc('id')->limit(self::PAGE + 1)->get();
        $page = $rows->take(self::PAGE);
        $last = $page->last();

        return Inertia::render('Fotos/Index', [
            'filters' => $filters,
            'photos' => Inertia::merge($page->map(fn (Photo $p) => $this->card($p))->values()->all()),
            'next' => $rows->count() > self::PAGE && $last ? $last->taken_at->utc()->format('Y-m-d H:i:s').'|'.$last->id : null,
            'tags' => $this->tagsWithCounts($family->id, $baby->id),
            'months' => $this->months($baby->id, $clock),
            'quota' => ['used_mb' => round($storage->usage($family->id) / 1048576, 1), 'total_mb' => $family->settings->photo_quota_mb],
        ]);
    }

    public function store(Request $request, PhotoStorage $storage): RedirectResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:20'],
            'photos.*' => ['file', 'max:15360', 'mimetypes:image/jpeg,image/png,image/webp'],
            'tags' => ['nullable', 'array', 'max:15'],
            'tags.*' => ['nullable', 'string', 'max:40'],
        ], trans('ui.fotos.upload_errors'));

        $baby = ActiveBaby::for($request);
        $tagIds = $this->resolveTags($request->user()->family_id, $request->input('tags', []));

        $count = 0;
        foreach ($request->file('photos') as $file) {
            $photo = $storage->store($file, $baby, $request->user());
            $photo->tags()->sync($tagIds);
            $count++;
        }

        return back()->with('status', trans_choice('ui.fotos.uploaded', $count, ['n' => $count]));
    }

    public function show(Request $request, Photo $photo): Response
    {
        $this->authorizePhoto($request, $photo);
        $clock = FamilyClock::for($request->user()->family);
        $photo->load(['tags:id,name', 'event:id,type,title,occurred_on', 'uploader:id,name']);

        $newer = Photo::where('baby_id', $photo->baby_id)
            ->where(fn ($q) => $q->where('taken_at', '>', $photo->taken_at)->orWhere(fn ($q) => $q->where('taken_at', $photo->taken_at)->where('id', '>', $photo->id)))
            ->orderBy('taken_at')->orderBy('id')->value('id');
        $older = Photo::where('baby_id', $photo->baby_id)
            ->where(fn ($q) => $q->where('taken_at', '<', $photo->taken_at)->orWhere(fn ($q) => $q->where('taken_at', $photo->taken_at)->where('id', '<', $photo->id)))
            ->orderByDesc('taken_at')->orderByDesc('id')->value('id');

        return Inertia::render('Fotos/Show', [
            'photo' => $this->card($photo) + [
                'url' => route('photos.file', $photo),
                'taken_local' => $clock->toLocal($photo->taken_at),
                'width' => $photo->width,
                'height' => $photo->height,
                'caption' => $photo->caption,
                'latitude' => $photo->latitude,
                'longitude' => $photo->longitude,
                'by' => $photo->uploader?->name,
                'event' => $photo->event ? ['id' => $photo->event->id, 'type' => $photo->event->type->value, 'title' => $photo->event->title] : null,
            ],
            'newer_id' => $newer,
            'older_id' => $older,
            'suggestions' => Tag::where('family_id', $photo->family_id)->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, Photo $photo): RedirectResponse
    {
        $this->authorizePhoto($request, $photo);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:500'],
            'taken_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'place_name' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array', 'max:15'],
            'tags.*' => ['nullable', 'string', 'max:40'],
        ], trans('ui.validation'), trans('ui.fotos.fields'));

        $photo->update([
            'caption' => $data['caption'] ?? null,
            'taken_at' => FamilyClock::for($request->user()->family)->parse($data['taken_at']),
            'place_name' => $data['place_name'] ?? null,
        ]);
        $photo->tags()->sync($this->resolveTags($photo->family_id, $data['tags'] ?? []));

        return back()->with('status', trans('ui.fotos.saved'));
    }

    public function favorite(Request $request, Photo $photo): RedirectResponse
    {
        $this->authorizePhoto($request, $photo);
        $photo->update(['is_favorite' => ! $photo->is_favorite]);

        return back();
    }

    /** Quita ubicación y nombre del lugar con un toque. */
    public function hideLocation(Request $request, Photo $photo): RedirectResponse
    {
        $this->authorizePhoto($request, $photo);
        $photo->update(['latitude' => null, 'longitude' => null, 'place_name' => null]);

        return back()->with('status', trans('ui.fotos.location_hidden'));
    }

    public function destroy(Request $request, Photo $photo): RedirectResponse
    {
        $this->authorizePhoto($request, $photo);
        $photo->delete();

        return redirect()->route('photos.index')->with('status', trans('ui.fotos.deleted'));
    }

    public function file(Request $request, Photo $photo): StreamedResponse
    {
        $this->authorizePhoto($request, $photo);

        return $this->stream($photo->path, $photo->mime, $photo->original_name);
    }

    public function thumb(Request $request, Photo $photo): StreamedResponse
    {
        $this->authorizePhoto($request, $photo);

        return $this->stream($photo->thumb_path, 'image/jpeg', 'miniatura.jpg');
    }

    private function stream(string $path, string $mime, string $name): StreamedResponse
    {
        return Storage::disk('local')->response($path, $name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=604800',
        ], 'inline');
    }

    private function authorizePhoto(Request $request, Photo $photo): void
    {
        abort_unless($photo->family_id === $request->user()->family_id, 404);
    }

    /** @return array<string, mixed> */
    private function card(Photo $photo): array
    {
        return [
            'id' => $photo->id,
            'taken_at' => $photo->taken_at->utc()->toIso8601String(),
            'thumb_url' => route('photos.thumb', $photo),
            'favorite' => $photo->is_favorite,
            'place_name' => $photo->place_name,
            'has_location' => $photo->hasLocation(),
            'tags' => $photo->relationLoaded('tags') ? $photo->tags->pluck('name')->values() : [],
        ];
    }

    /** @param  array<int, string>  $names
     * @return array<int, int> */
    private function resolveTags(int $familyId, array $names): array
    {
        return collect($names)
            ->map(fn ($n) => Tag::normalize((string) $n))
            ->filter()->unique()->take(15)
            ->map(fn ($name) => Tag::firstOrCreate(['family_id' => $familyId, 'name' => $name])->id)
            ->values()->all();
    }

    private function tagsWithCounts(int $familyId, int $babyId): array
    {
        return Tag::where('family_id', $familyId)
            ->withCount(['photos' => fn ($q) => $q->where('baby_id', $babyId)])
            ->orderBy('name')->get()
            ->filter(fn ($t) => $t->photos_count > 0)
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'count' => $t->photos_count])
            ->values()->all();
    }

    /** Meses (en hora de la familia) en los que hay fotos, del más reciente al más antiguo. */
    private function months(int $babyId, FamilyClock $clock): array
    {
        return Photo::where('baby_id', $babyId)->pluck('taken_at')
            ->map(fn ($at) => CarbonImmutable::instance($at)->setTimezone($clock->timezone)->format('Y-m'))
            ->unique()->sortDesc()->values()->all();
    }

    /** @return array{0: string, 1: int}|null */
    private function parseCursor(mixed $cursor): ?array
    {
        if (! is_string($cursor) || ! preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\|(\d+)$/', $cursor, $m)) {
            return null;
        }

        return [$m[1], (int) $m[2]];
    }
}
