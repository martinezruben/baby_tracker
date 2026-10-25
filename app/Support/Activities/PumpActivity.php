<?php

namespace App\Support\Activities;

use App\Models\PumpLog;
use App\Support\FamilyClock;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PumpActivity extends ActivityType
{
    public function slug(): string
    {
        return 'extraccion';
    }

    public function model(): string
    {
        return PumpLog::class;
    }

    public function timeColumn(): string
    {
        return 'started_at';
    }

    public function rules(ActivityContext $context): array
    {
        return [
            'occurred_at' => $this->occurredAtRules($context),
            'duration_minutes' => ['nullable', 'integer', 'between:1,240'],
            'left_amount' => ['required', 'numeric', 'between:0,1000'],
            'right_amount' => [
                'required', 'numeric', 'between:0,1000',
                // Al menos un lado con leche.
                fn (string $attribute, mixed $value, Closure $fail) => (float) $value + (float) request()->input('left_amount') > 0 || $fail(trans('ui.registrar.pump.need_amount')),
            ],
            'unit' => ['required', Rule::in(['ml', 'oz'])],
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
            'left_amount' => $data['left_amount'],
            'right_amount' => $data['right_amount'],
            'total_amount' => (float) $data['left_amount'] + (float) $data['right_amount'],
            'unit' => $data['unit'],
        ];
    }

    public function formValues(Model $log, FamilyClock $clock): array
    {
        return [
            'occurred_at' => $clock->toLocal($log->started_at),
            'duration_minutes' => $log->duration_minutes ?? '',
            'left_amount' => (float) $log->left_amount,
            'right_amount' => (float) $log->right_amount,
            'unit' => $log->unit,
        ];
    }

    public function summary(Model $log): array
    {
        return ['amount' => (float) $log->total_amount, 'unit' => $log->unit];
    }
}
