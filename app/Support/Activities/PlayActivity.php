<?php

namespace App\Support\Activities;

use App\Enums\PlayType;
use App\Models\PlayLog;
use App\Support\FamilyClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PlayActivity extends ActivityType
{
    public function slug(): string
    {
        return 'juego';
    }

    public function model(): string
    {
        return PlayLog::class;
    }

    public function timeColumn(): string
    {
        return 'started_at';
    }

    public function rules(ActivityContext $context): array
    {
        return [
            'occurred_at' => $this->occurredAtRules($context),
            'type' => ['required', Rule::enum(PlayType::class)],
            'duration_minutes' => ['nullable', 'integer', 'between:1,720'],
            'activities' => ['nullable', 'string', 'max:300'],
        ];
    }

    protected function attributes(ActivityContext $context, array $data, ?Model $log = null): array
    {
        $start = $context->clock->parse($data['occurred_at']);
        $minutes = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null;

        return [
            'started_at' => $start,
            'ended_at' => $minutes ? $start->addMinutes($minutes) : null,
            'duration_minutes' => $minutes,
            'type' => $data['type'],
            'activities' => $data['activities'] ?? null,
        ];
    }

    public function formValues(Model $log, FamilyClock $clock): array
    {
        return [
            'occurred_at' => $clock->toLocal($log->started_at),
            'type' => $log->type->value,
            'duration_minutes' => $log->duration_minutes ?? '',
            'activities' => $log->activities ?? '',
        ];
    }

    public function summary(Model $log): array
    {
        return ['type' => $log->type->value];
    }
}
