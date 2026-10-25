<?php

namespace App\Support\Activities;

use App\Enums\FoodEnjoyment;
use App\Models\Food;
use App\Models\FoodLog;
use App\Support\FamilyClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SolidsActivity extends ActivityType
{
    public function slug(): string
    {
        return 'solidos';
    }

    public function model(): string
    {
        return FoodLog::class;
    }

    public function timeColumn(): string
    {
        return 'occurred_at';
    }

    public function rules(ActivityContext $context): array
    {
        return [
            'occurred_at' => $this->occurredAtRules($context),
            'food_id' => ['nullable', 'required_without:new_food', Rule::exists(Food::class, 'id')->where('family_id', $context->baby->family_id)->whereNull('deleted_at')],
            'new_food' => ['nullable', 'required_without:food_id', 'string', 'max:80'],
            'amount' => ['nullable', 'numeric', 'between:0.1,1000'],
            'unit' => ['nullable', Rule::in(['g', 'oz', 'tbsp'])],
            'enjoyment' => ['nullable', Rule::enum(FoodEnjoyment::class)],
            'had_reaction' => ['sometimes', 'boolean'],
            'reaction_notes' => ['nullable', 'string', 'max:300'],
        ];
    }

    protected function attributes(ActivityContext $context, array $data, ?Model $log = null): array
    {
        $food = ! empty($data['new_food'])
            ? Food::firstOrCreate(['family_id' => $context->baby->family_id, 'name' => trim($data['new_food'])])
            : Food::findOrFail($data['food_id']);

        $reaction = (bool) ($data['had_reaction'] ?? false);

        return [
            'food_id' => $food->id,
            'occurred_at' => $context->clock->parse($data['occurred_at']),
            'amount' => $data['amount'] ?? null,
            'unit' => isset($data['amount']) ? ($data['unit'] ?? 'g') : null,
            'enjoyment' => $data['enjoyment'] ?? null,
            'had_reaction' => $reaction,
            'reaction_notes' => $reaction ? ($data['reaction_notes'] ?? null) : null,
        ];
    }

    public function formValues(Model $log, FamilyClock $clock): array
    {
        return [
            'occurred_at' => $clock->toLocal($log->occurred_at),
            'food_id' => $log->food_id,
            'new_food' => '',
            'amount' => $log->amount === null ? '' : (float) $log->amount,
            'unit' => $log->unit ?? 'g',
            'enjoyment' => $log->enjoyment?->value ?? '',
            'had_reaction' => (bool) $log->had_reaction,
            'reaction_notes' => $log->reaction_notes ?? '',
        ];
    }

    public function summary(Model $log): array
    {
        return ['food' => Food::find($log->food_id)?->name];
    }
}
