<?php

namespace App\Support\Activities;

use App\Enums\MeasurementType;
use App\Models\Measurement;
use App\Support\FamilyClock;
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

    protected function attributes(ActivityContext $context, array $data, ?Model $log = null): array
    {
        $settings = $context->baby->family->settings;
        $type = MeasurementType::from($data['type']);

        return [
            'measured_on' => $context->clock->parse($data['occurred_at'])->setTimezone($context->clock->timezone)->toDateString(),
            'type' => $type,
            'value' => $data['value'],
            'unit' => match ($type) {
                MeasurementType::Weight => $settings->weight_unit,
                MeasurementType::Temperature => $settings->temperature_unit,
                default => $settings->height_unit,
            },
        ];
    }

    public function formValues(Model $log, FamilyClock $clock): array
    {
        return [
            'occurred_at' => $log->measured_on->format('Y-m-d').'T'.$clock->now()->format('H:i'),
            'type' => $log->type->value,
            'value' => (float) $log->value,
        ];
    }

    public function summary(Model $log): array
    {
        return ['type' => $log->type->value, 'value' => (float) $log->value, 'unit' => $log->unit];
    }
}
