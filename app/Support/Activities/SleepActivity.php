<?php

namespace App\Support\Activities;

use App\Enums\SleepQuality;
use App\Enums\SleepType;
use App\Models\SleepLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SleepActivity extends ActivityType
{
    public function slug(): string
    {
        return 'sueno';
    }

    public function model(): string
    {
        return SleepLog::class;
    }

    public function timeColumn(): string
    {
        return 'started_at';
    }

    public function rules(ActivityContext $context): array
    {
        return [
            // "occurred_at" es la hora en que se durmió.
            'occurred_at' => $this->occurredAtRules($context),
            'in_progress' => ['required', 'boolean'],
            'ended_at' => ['nullable', 'required_if:in_progress,false', 'date_format:Y-m-d\TH:i', 'after_or_equal:occurred_at'],
            'type' => ['required', Rule::enum(SleepType::class)],
            'quality' => ['nullable', Rule::enum(SleepQuality::class)],
            'location' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function create(ActivityContext $context, array $data): Model
    {
        $start = $context->clock->parse($data['occurred_at']);
        $inProgress = (bool) $data['in_progress'];

        if ($inProgress && SleepLog::where('baby_id', $context->baby->id)->whereNull('ended_at')->exists()) {
            throw ValidationException::withMessages(['in_progress' => trans('ui.registrar.sleep.already_open')]);
        }

        $end = $inProgress ? null : $context->clock->parse($data['ended_at']);

        return SleepLog::create($context->owner() + [
            'started_at' => $start,
            'ended_at' => $end,
            'duration_minutes' => $end ? (int) $start->diffInMinutes($end) : null,
            'type' => $data['type'],
            'quality' => $data['quality'] ?? null,
            'location' => $data['location'] ?? null,
        ]);
    }
}
