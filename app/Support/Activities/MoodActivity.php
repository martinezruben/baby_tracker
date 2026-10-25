<?php

namespace App\Support\Activities;

use App\Enums\Mood;
use App\Models\MoodLog;
use App\Support\FamilyClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class MoodActivity extends ActivityType
{
    public function slug(): string
    {
        return 'animo';
    }

    public function model(): string
    {
        return MoodLog::class;
    }

    public function timeColumn(): string
    {
        return 'occurred_at';
    }

    public function rules(ActivityContext $context): array
    {
        return [
            'occurred_at' => $this->occurredAtRules($context),
            'mood' => ['required', Rule::enum(Mood::class)],
            'intensity' => ['nullable', 'integer', 'between:1,5'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,720'],
        ];
    }

    protected function attributes(ActivityContext $context, array $data, ?Model $log = null): array
    {
        return [
            'occurred_at' => $context->clock->parse($data['occurred_at']),
            'mood' => $data['mood'],
            'intensity' => $data['intensity'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
        ];
    }

    public function formValues(Model $log, FamilyClock $clock): array
    {
        return [
            'occurred_at' => $clock->toLocal($log->occurred_at),
            'mood' => $log->mood->value,
            'intensity' => $log->intensity ?? '',
            'duration_minutes' => $log->duration_minutes ?? '',
        ];
    }

    public function summary(Model $log): array
    {
        return ['mood' => $log->mood->value];
    }
}
