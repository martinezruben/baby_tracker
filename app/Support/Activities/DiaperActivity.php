<?php

namespace App\Support\Activities;

use App\Enums\DiaperType;
use App\Models\DiaperLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class DiaperActivity extends ActivityType
{
    public function slug(): string
    {
        return 'panal';
    }

    public function model(): string
    {
        return DiaperLog::class;
    }

    public function timeColumn(): string
    {
        return 'occurred_at';
    }

    public function rules(ActivityContext $context): array
    {
        return [
            'occurred_at' => $this->occurredAtRules($context),
            'type' => ['required', Rule::enum(DiaperType::class)],
            'cream_applied' => ['sometimes', 'boolean'],
            'blowout' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function create(ActivityContext $context, array $data): Model
    {
        return DiaperLog::create($context->owner() + [
            'occurred_at' => $context->clock->parse($data['occurred_at']),
            'type' => $data['type'],
            'cream_applied' => $data['cream_applied'] ?? false,
            'blowout' => $data['blowout'] ?? false,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function summary(Model $log): array
    {
        return ['type' => $log->type->value];
    }

    public function repeatable(): bool
    {
        return true;
    }

    public function repeat(ActivityContext $context, Model $last): Model
    {
        return DiaperLog::create($context->owner() + [
            'occurred_at' => now(),
            'type' => $last->type,
            'cream_applied' => $last->cream_applied,
        ]);
    }
}
