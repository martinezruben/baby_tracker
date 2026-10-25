<?php

namespace App\Http\Requests;

use App\Models\Medicine;
use App\Support\FamilyClock;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrescriptionRequest extends FormRequest
{
    public const INTERVALS = [4, 6, 8, 12, 24];

    public function rules(): array
    {
        $clock = FamilyClock::for($this->user()->family);

        return [
            'medicine_id' => ['nullable', 'required_without:new_medicine', Rule::exists(Medicine::class, 'id')->where('family_id', $this->user()->family_id)->whereNull('deleted_at')],
            'new_medicine' => ['nullable', 'required_without:medicine_id', 'string', 'max:80'],
            'dose' => ['required', 'numeric', 'between:0.01,10000'],
            'unit' => ['nullable', 'string', 'max:8'],
            'interval_hours' => ['required', 'integer', 'between:1,72'],
            'total_doses' => ['required', 'integer', 'between:1,120'],
            'starts_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
                function (string $attribute, mixed $value, Closure $fail) use ($clock) {
                    try {
                        $at = $clock->parse((string) $value);
                    } catch (\Throwable) {
                        return;
                    }

                    // La primera toma no puede haber sido hace más de un día (evita recetas "retroactivas" enormes).
                    if ($at->lessThan(now()->subDay())) {
                        $fail(trans('ui.citas.errors.start_too_old'));
                    }
                },
            ],
            'instructions' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return trans('ui.validation');
    }

    public function attributes(): array
    {
        return trans('ui.citas.fields');
    }

    /** @return array<string, mixed> */
    public function prescriptionData(): array
    {
        $data = $this->validated();

        return [
            'medicine_id' => $data['medicine_id'] ?? null,
            'new_medicine' => $data['new_medicine'] ?? null,
            'dose' => $data['dose'],
            'unit' => $data['unit'] ?? null,
            'interval_hours' => (int) $data['interval_hours'],
            'total_doses' => (int) $data['total_doses'],
            'starts_at' => FamilyClock::for($this->user()->family)->parse($data['starts_at']),
            'instructions' => $data['instructions'] ?? null,
        ];
    }
}
