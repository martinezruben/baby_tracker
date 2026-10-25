<?php

namespace App\Support\Activities;

use App\Models\Medicine;
use App\Models\MedicineLog;
use App\Support\FamilyClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class MedicineActivity extends ActivityType
{
    public function slug(): string
    {
        return 'medicina';
    }

    public function model(): string
    {
        return MedicineLog::class;
    }

    public function timeColumn(): string
    {
        return 'occurred_at';
    }

    public function rules(ActivityContext $context): array
    {
        $family = $context->baby->family_id;

        return [
            'occurred_at' => $this->occurredAtRules($context),
            'medicine_id' => ['nullable', 'required_without:new_medicine', Rule::exists(Medicine::class, 'id')->where('family_id', $family)->whereNull('deleted_at')],
            'new_medicine' => ['nullable', 'required_without:medicine_id', 'string', 'max:80'],
            'dose' => ['required', 'numeric', 'between:0.01,10000'],
            'unit' => ['nullable', 'string', 'max:8'],
        ];
    }

    protected function attributes(ActivityContext $context, array $data, ?Model $log = null): array
    {
        $medicine = ! empty($data['new_medicine'])
            ? Medicine::firstOrCreate(
                ['family_id' => $context->baby->family_id, 'name' => trim($data['new_medicine'])],
                ['typical_dose' => $data['dose'], 'unit' => $data['unit'] ?? null],
            )
            : Medicine::findOrFail($data['medicine_id']);

        return [
            'medicine_id' => $medicine->id,
            'occurred_at' => $context->clock->parse($data['occurred_at']),
            'dose' => $data['dose'],
            'unit' => $data['unit'] ?? $medicine->unit,
        ];
    }

    public function formValues(Model $log, FamilyClock $clock): array
    {
        return [
            'occurred_at' => $clock->toLocal($log->occurred_at),
            'medicine_id' => $log->medicine_id,
            'new_medicine' => '',
            'dose' => (float) $log->dose,
            'unit' => $log->unit ?? '',
        ];
    }

    public function summary(Model $log): array
    {
        return ['name' => $log->medicine?->name, 'dose' => (float) $log->dose, 'unit' => $log->unit];
    }

    public function repeatable(): bool
    {
        return true;
    }

    public function repeat(ActivityContext $context, Model $last): Model
    {
        return MedicineLog::create($context->owner() + [
            'medicine_id' => $last->medicine_id,
            'occurred_at' => now(),
            'dose' => $last->dose,
            'unit' => $last->unit,
        ]);
    }
}
