<?php

namespace App\Support;

use App\Enums\FeedType;
use App\Models\Baby;
use App\Models\DiaperLog;
use App\Models\FeedLog;
use App\Models\Measurement;
use App\Models\MedicineLog;
use App\Models\Note;
use App\Models\SleepLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Línea de tiempo unificada de un bebé (Bitácora): mezcla los registros de todos los tipos,
 * del más reciente al más antiguo, paginada con un cursor "instante|tipo|id" que no pierde
 * ni repite registros aunque varios compartan el mismo segundo.
 */
class Timeline
{
    public const PAGE = 30;

    /** @var array<string, array{0: class-string<Model>, 1: string, 2: ?callable}> tipo => [modelo, columna de fecha, restricción] */
    private function sources(): array
    {
        return [
            'biberon' => [FeedLog::class, 'occurred_at', fn (Builder $q) => $q->where('type', FeedType::Bottle)],
            'pecho' => [FeedLog::class, 'occurred_at', fn (Builder $q) => $q->where('type', FeedType::Breast)],
            'panal' => [DiaperLog::class, 'occurred_at', null],
            'sueno' => [SleepLog::class, 'started_at', null],
            'medicina' => [MedicineLog::class, 'occurred_at', fn (Builder $q) => $q->with('medicine:id,name')],
            // Las medidas solo guardan el día; se ordenan por cuándo se registraron.
            'medida' => [Measurement::class, 'created_at', null],
            'nota' => [Note::class, 'occurred_at', null],
        ];
    }

    /** @return array<int, string> */
    public function types(): array
    {
        return array_keys($this->sources());
    }

    /**
     * @return array{entries: array<int, array<string, mixed>>, next: ?string}
     */
    public function page(Baby $baby, ?string $type = null, ?string $cursor = null): array
    {
        [$cursorAt, $cursorType, $cursorId] = $this->parseCursor($cursor);

        $entries = collect($this->sources())
            ->filter(fn ($source, $slug) => $type === null || $type === $slug)
            ->flatMap(function (array $source, string $slug) use ($baby, $cursorAt) {
                [$model, $column, $scope] = $source;

                $query = $model::query()->where('baby_id', $baby->id)->with('caretaker:id,name');
                if ($scope) {
                    $scope($query);
                }
                if ($cursorAt) {
                    $query->where($column, '<=', $cursorAt);
                }

                return $query->orderByDesc($column)->orderByDesc('id')->limit(self::PAGE + 10)->get()
                    ->map(fn (Model $log) => $this->entry($slug, $log, $column));
            })
            ->sort(fn ($a, $b) => [$b['at'], $a['type'], $b['id']] <=> [$a['at'], $b['type'], $a['id']])
            ->values();

        if ($cursorAt) {
            // Quedarse con lo que va estrictamente después del cursor en el orden de la lista.
            $entries = $entries->filter(fn ($e) => $this->isAfterCursor($e, $cursorAt, $cursorType, $cursorId))->values();
        }

        $page = $entries->take(self::PAGE)->values();
        $hasMore = $entries->count() > self::PAGE;
        $last = $page->last();

        return [
            'entries' => $page->all(),
            'next' => $hasMore && $last ? "{$last['at']}|{$last['type']}|{$last['id']}" : null,
        ];
    }

    /** Orden de la lista: instante descendente, luego tipo ascendente, luego id descendente. */
    private function isAfterCursor(array $e, CarbonImmutable $at, string $type, int $id): bool
    {
        $entryAt = CarbonImmutable::parse($e['at']);

        if ($entryAt->notEqualTo($at)) {
            return $entryAt->lessThan($at);
        }

        return $e['type'] === $type ? $e['id'] < $id : $e['type'] > $type;
    }

    /** @return array{0: ?CarbonImmutable, 1: string, 2: int} */
    private function parseCursor(?string $cursor): array
    {
        $parts = $cursor ? explode('|', $cursor) : [];

        if (count($parts) !== 3 || ! ctype_digit($parts[2])) {
            return [null, '', 0];
        }

        try {
            return [CarbonImmutable::parse($parts[0])->utc(), $parts[1], (int) $parts[2]];
        } catch (\Throwable) {
            return [null, '', 0];
        }
    }

    private function entry(string $slug, Model $log, string $column): array
    {
        return [
            'type' => $slug,
            'id' => $log->id,
            'at' => CarbonImmutable::instance($log->{$column})->utc()->toIso8601String(),
            'by' => $log->caretaker?->name,
            'data' => $this->data($slug, $log),
        ];
    }

    private function data(string $slug, Model $log): array
    {
        return match ($slug) {
            'biberon' => ['amount' => (float) $log->amount, 'unit' => $log->unit, 'bottle_type' => $log->bottle_type, 'notes' => $log->notes],
            'pecho' => [
                'seconds' => (int) $log->duration_seconds, 'left' => $log->left_seconds, 'right' => $log->right_seconds,
                'side' => $log->side?->value,
            ],
            'panal' => ['type' => $log->type->value, 'cream' => $log->cream_applied, 'blowout' => $log->blowout, 'notes' => $log->notes],
            'sueno' => [
                'minutes' => $log->duration_minutes, 'open' => $log->ended_at === null, 'type' => $log->type->value,
                'ended_at' => $log->ended_at?->toIso8601String(), 'location' => $log->location,
            ],
            'medicina' => ['name' => $log->medicine?->name, 'dose' => (float) $log->dose, 'unit' => $log->unit],
            'medida' => ['type' => $log->type->value, 'value' => (float) $log->value, 'unit' => $log->unit],
            'nota' => ['content' => $log->content],
        };
    }
}
