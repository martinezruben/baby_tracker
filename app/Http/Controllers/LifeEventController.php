<?php

namespace App\Http\Controllers;

use App\Enums\FoodEnjoyment;
use App\Enums\LifeEventType;
use App\Models\LifeEvent;
use App\Models\Photo;
use App\Support\ActiveBaby;
use App\Support\FamilyClock;
use App\Support\PhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LifeEventController extends Controller
{
    public function index(Request $request): Response
    {
        $baby = ActiveBaby::for($request);

        $type = LifeEventType::tryFrom((string) $request->query('tipo'));

        $events = LifeEvent::where('baby_id', $baby->id)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->with(['photos' => fn ($q) => $q->limit(4)])
            ->orderByDesc('occurred_on')->orderByDesc('id')->limit(200)->get();

        return Inertia::render('Eventos/Index', [
            'tipo' => $type?->value,
            // Cuántos momentos hay de cada tipo: los filtros solo ofrecen los que existen.
            'counts' => LifeEvent::where('baby_id', $baby->id)->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type'),
            'events' => $events->map(fn (LifeEvent $e) => $this->card($e))->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Eventos/Form', $this->formProps($request));
    }

    public function store(Request $request, PhotoStorage $storage): RedirectResponse
    {
        $data = $this->validated($request, withPhotos: true);
        $baby = ActiveBaby::for($request);

        $event = LifeEvent::create($this->fields($data) + [
            'family_id' => $baby->family_id,
            'baby_id' => $baby->id,
            'created_by' => $request->user()->id,
        ]);

        foreach ($request->file('photos', []) as $file) {
            $storage->store($file, $baby, $request->user(), [
                'life_event_id' => $event->id,
                'taken_at' => $event->occurred_on->setTimezone('UTC')->setTime(12, 0), // sin EXIF: el día del evento
            ]);
        }

        return redirect()->route('events.show', $event)->with('status', trans('ui.eventos.saved'));
    }

    public function show(Request $request, LifeEvent $event): Response
    {
        $this->authorizeEvent($request, $event);
        $event->load('photos.tags:id,name');

        return Inertia::render('Eventos/Show', [
            'event' => $this->card($event, allPhotos: true),
        ]);
    }

    public function edit(Request $request, LifeEvent $event): Response
    {
        $this->authorizeEvent($request, $event);

        return Inertia::render('Eventos/Form', $this->formProps($request) + [
            'event' => [
                'id' => $event->id,
                'type' => $event->type->value,
                'occurred_on' => $event->occurred_on->toDateString(),
                'title' => $event->title,
                'detail' => $event->detail,
                'seasonings' => $event->seasonings,
                'rating' => $event->rating,
                'notes' => $event->notes,
            ],
        ]);
    }

    public function update(Request $request, LifeEvent $event): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $event->update($this->fields($this->validated($request)));

        return redirect()->route('events.show', $event)->with('status', trans('ui.eventos.saved'));
    }

    public function destroy(Request $request, LifeEvent $event): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        // Las fotos se quedan en la galería, solo pierden el vínculo con el evento.
        $event->photos()->update(['life_event_id' => null]);
        $event->delete();

        return redirect()->route('events.index')->with('status', trans('ui.eventos.deleted'));
    }

    public function addPhotos(Request $request, LifeEvent $event, PhotoStorage $storage): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:20'],
            'photos.*' => ['file', 'max:15360', 'mimetypes:image/jpeg,image/png,image/webp'],
        ], trans('ui.fotos.upload_errors'));

        foreach ($request->file('photos') as $file) {
            $storage->store($file, $event->baby, $request->user(), [
                'life_event_id' => $event->id,
                'taken_at' => $event->occurred_on->setTimezone('UTC')->setTime(12, 0),
            ]);
        }

        return back()->with('status', trans_choice('ui.fotos.uploaded', count($request->file('photos')), ['n' => count($request->file('photos'))]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $withPhotos = false): array
    {
        $clock = FamilyClock::for($request->user()->family);

        $rules = [
            'type' => ['required', Rule::enum(LifeEventType::class)],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$clock->now()->addDay()->toDateString()],
            'title' => ['nullable', 'string', 'max:120', 'required_if:type,other'],
            'detail' => ['nullable', 'string', 'max:120'],
            'seasonings' => ['nullable', 'string', 'max:200'],
            'rating' => ['nullable', Rule::enum(FoodEnjoyment::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($withPhotos) {
            $rules['photos'] = ['nullable', 'array', 'max:20'];
            $rules['photos.*'] = ['file', 'max:15360', 'mimetypes:image/jpeg,image/png,image/webp'];
        }

        return $request->validate($rules, trans('ui.fotos.upload_errors') + trans('ui.validation'), trans('ui.eventos.fields'));
    }

    /** @param  array<string, mixed>  $data */
    private function fields(array $data): array
    {
        return [
            'type' => $data['type'],
            'occurred_on' => $data['occurred_on'],
            'title' => $data['title'] ?? null,
            'detail' => $data['detail'] ?? null,
            // Condimentos y calificación solo tienen sentido en una comida nueva.
            'seasonings' => $data['type'] === 'food' ? ($data['seasonings'] ?? null) : null,
            'rating' => $data['type'] === 'food' ? ($data['rating'] ?? null) : null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function authorizeEvent(Request $request, LifeEvent $event): void
    {
        abort_unless($event->family_id === $request->user()->family_id, 404);
    }

    private function formProps(Request $request): array
    {
        $baby = ActiveBaby::for($request);
        $clock = FamilyClock::for($request->user()->family);

        return [
            'baby' => $baby->only(['id', 'first_name']),
            'today' => $clock->now()->toDateString(),
            'types' => array_map(fn ($c) => $c->value, LifeEventType::cases()),
        ];
    }

    /** @return array<string, mixed> */
    private function card(LifeEvent $event, bool $allPhotos = false): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type->value,
            'occurred_on' => $event->occurred_on->toDateString(),
            'title' => $event->title,
            'detail' => $event->detail,
            'seasonings' => $event->seasonings,
            'rating' => $event->rating,
            'notes' => $event->notes,
            'photos' => $event->photos->map(fn (Photo $p) => [
                'id' => $p->id,
                'thumb_url' => route('photos.thumb', $p),
                'url' => route('photos.file', $p),
            ])->values(),
        ];
    }
}
