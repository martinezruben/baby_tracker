<?php

namespace App\Support\Activities;

use App\Enums\FeedType;
use App\Models\FeedLog;
use App\Support\FamilyClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class BottleActivity extends ActivityType
{
    public function slug(): string
    {
        return 'biberon';
    }

    public function model(): string
    {
        return FeedLog::class;
    }

    public function timeColumn(): string
    {
        return 'occurred_at';
    }

    public function query(int $babyId): Builder
    {
        return FeedLog::query()->where('baby_id', $babyId)->where('type', FeedType::Bottle);
    }

    public function rules(ActivityContext $context): array
    {
        return [
            'occurred_at' => $this->occurredAtRules($context),
            'amount' => ['required', 'numeric', 'between:1,1000'],
            'unit' => ['required', Rule::in(['ml', 'oz'])],
            'bottle_type' => ['nullable', Rule::in(['formula', 'breast_milk', 'other'])],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }

    protected function attributes(ActivityContext $context, array $data, ?Model $log = null): array
    {
        $at = $context->clock->parse($data['occurred_at']);

        return [
            'occurred_at' => $at,
            'started_at' => $at,
            'type' => FeedType::Bottle,
            'amount' => $data['amount'],
            'unit' => $data['unit'],
            'bottle_type' => $data['bottle_type'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    public function formValues(Model $log, FamilyClock $clock): array
    {
        return [
            'occurred_at' => $clock->toLocal($log->occurred_at),
            'amount' => (float) $log->amount,
            'unit' => $log->unit,
            'bottle_type' => $log->bottle_type ?? '',
            'notes' => $log->notes ?? '',
        ];
    }

    /** Los registros de biberón son los de tipo bottle; el pecho tiene su propia sesión. */
    public function summary(Model $log): array
    {
        return ['amount' => (float) $log->amount, 'unit' => $log->unit];
    }

    public function repeatable(): bool
    {
        return true;
    }

    public function repeat(ActivityContext $context, Model $last): Model
    {
        return FeedLog::create($context->owner() + [
            'occurred_at' => now(),
            'started_at' => now(),
            'type' => FeedType::Bottle,
            'amount' => $last->amount,
            'unit' => $last->unit,
            'bottle_type' => $last->bottle_type,
        ]);
    }
}
