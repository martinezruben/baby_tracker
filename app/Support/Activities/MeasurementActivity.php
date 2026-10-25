<?php

namespace App\Support\Activities;

use App\Enums\MeasurementType;
use App\Models\Measurement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class MeasurementActivity extends ActivityType
{
    public function slug(): string
    {
        return 'medida';
    }

    public function model(): string
    {
        return Measurement::class;
    }

    public function timeColumn(): string
    {
        return 'measured_on';
    }

    public function rules(ActivityContext $context): array
    {
        return [
            'occurred_at' => $this->occurredAtRules($context),
            'type' => ['required', Rule::enum(MeasurementType::class)],
            'value' => ['required', 'numeric', 'between:0.01,500'],
        ];
    }

    public function create(ActivityContext $context, array $data): Model
    {
        $settings = $context->baby->family->settings;
        $type = MeasurementType::from($data['type']);

        return Measurement::create($context->owner() + [
            'measured_on' => $context->clock->parse($data['occurred_at'])->setTimezone($context->clock->timezone)->toDateString(),
            'type' => $type,
            'value' => $data['value'],
            'unit' => match ($type) {
                MeasurementType::Weight => $settings->weight_unit,
                MeasurementType::Temperature => $settings->temperature_unit,
                default => $settings->height_unit,
            },
        ]);
    }

    public function summary(Model $log): array
    {
        return ['type' => $log->type->value, 'value' => (float) $log->value, 'unit' => $log->unit];
    }
}
