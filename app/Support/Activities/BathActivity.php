<?php

namespace App\Support\Activities;

use App\Models\BathLog;
use App\Support\FamilyClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class BathActivity extends ActivityType
{
    public function slug(): string
    {
        return 'bano';
    }

    public function model(): string
    {
        return BathLog::class;
    }

    public function timeColumn(): string
    {
        return 'occurred_at';
    }

    public function rules(ActivityContext $context): array
    {
        return [
            'occurred_at' => $this->occurredAtRules($context),
            'bath_type' => ['nullable', Rule::in(['full', 'sponge'])],
            'soap_used' => ['sometimes', 'boolean'],
            'shampoo_used' => ['sometimes', 'boolean'],
        ];
    }

    protected function attributes(ActivityContext $context, array $data, ?Model $log = null): array
    {
        return [
            'occurred_at' => $context->clock->parse($data['occurred_at']),
            'bath_type' => $data['bath_type'] ?? null,
            'soap_used' => $data['soap_used'] ?? false,
            'shampoo_used' => $data['shampoo_used'] ?? false,
        ];
    }

    public function formValues(Model $log, FamilyClock $clock): array
    {
        return [
            'occurred_at' => $clock->toLocal($log->occurred_at),
            'bath_type' => $log->bath_type ?? '',
            'soap_used' => (bool) $log->soap_used,
            'shampoo_used' => (bool) $log->shampoo_used,
        ];
    }

    public function summary(Model $log): array
    {
        return ['bath_type' => $log->bath_type];
    }

    public function repeatable(): bool
    {
        return true;
    }

    public function repeat(ActivityContext $context, Model $last): Model
    {
        return BathLog::create($context->owner() + [
            'occurred_at' => now(),
            'bath_type' => $last->bath_type,
            'soap_used' => $last->soap_used,
            'shampoo_used' => $last->shampoo_used,
        ]);
    }
}
