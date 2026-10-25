<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetupRequest extends FormRequest
{
    public const WEAK_PINS = ['111222', '123456', '000000', '111111', '1234', '0000', '1111'];

    public function rules(): array
    {
        return [
            'family_name' => ['required', 'string', 'max:80'],
            'language' => ['required', Rule::in(['es', 'en', 'fr', 'de', 'it'])],
            'admin_name' => ['required', 'string', 'max:80'],
            'pin' => ['required', 'string', 'regex:/^\d{4,10}$/', 'confirmed', Rule::notIn(self::WEAK_PINS)],
            'baby_first_name' => ['required', 'string', 'max:80'],
            'baby_birth_date' => ['required', 'date', 'before_or_equal:today'],
            'baby_gender' => ['nullable', Rule::enum(Gender::class)],
            'feed_warning_minutes' => ['nullable', 'integer', 'between:15,720'],
            'diaper_warning_minutes' => ['nullable', 'integer', 'between:15,720'],
        ];
    }

    public function messages(): array
    {
        return trans('ui.validation');
    }

    public function attributes(): array
    {
        return trans('ui.fields');
    }
}
