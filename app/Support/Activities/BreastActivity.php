<?php

namespace App\Support\Activities;

use App\Enums\BreastSide;
use App\Enums\FeedType;
use App\Models\FeedLog;
use App\Support\FamilyClock;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lactancia ya guardada. Se crea con el cronómetro (BreastFeedSession), así que este tipo no
 * está en ActivityRegistry: solo sirve para corregir un registro desde la Bitácora.
 */
class BreastActivity extends ActivityType
{
    public function slug(): string
    {
        return 'pecho';
    }

    public function model(): string
    {
        return FeedLog::class;
    }

    public function timeColumn(): string
    {
        return 'occurred_at';
    }

    public function page(): string
    {
        return 'Registrar/PechoEditar';
    }

    public function query(int $babyId): Builder
    {
        return FeedLog::query()->where('baby_id', $babyId)->where('type', FeedType::Breast);
    }

    public function rules(ActivityContext $context): array
    {
        return [
            'occurred_at' => $this->occurredAtRules($context),
            'left_minutes' => ['required', 'integer', 'between:0,240'],
            'right_minutes' => [
                'required', 'integer', 'between:0,240',
                // Al menos un lado con tiempo.
                fn (string $attribute, mixed $value, Closure $fail) => (int) $value + (int) request()->input('left_minutes') > 0 || $fail(trans('ui.registrar.breast_edit.need_time')),
            ],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }

    protected function attributes(ActivityContext $context, array $data, ?Model $log = null): array
    {
        $at = $context->clock->parse($data['occurred_at']);
        $left = (int) $data['left_minutes'] * 60;
        $right = (int) $data['right_minutes'] * 60;

        return [
            'occurred_at' => $at,
            'started_at' => $at,
            'ended_at' => $at->addSeconds($left + $right + (int) ($log?->pause_seconds ?? 0)),
            'type' => FeedType::Breast,
            'side' => match (true) {
                $left > 0 && $right === 0 => BreastSide::Left,
                $right > 0 && $left === 0 => BreastSide::Right,
                default => null,
            },
            'left_seconds' => $left,
            'right_seconds' => $right,
            'duration_seconds' => $left + $right,
            'notes' => $data['notes'] ?? null,
        ];
    }

    public function formValues(Model $log, FamilyClock $clock): array
    {
        return [
            'occurred_at' => $clock->toLocal($log->occurred_at),
            'left_minutes' => (int) round(($log->left_seconds ?? 0) / 60),
            'right_minutes' => (int) round(($log->right_seconds ?? 0) / 60),
            'notes' => $log->notes ?? '',
        ];
    }
}
