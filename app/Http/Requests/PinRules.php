<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class PinRules
{
    /** @return array<int, mixed> */
    public static function newPin(): array
    {
        return ['required', 'string', 'regex:/^\d{4,10}$/', 'confirmed', Rule::notIn(SetupRequest::WEAK_PINS)];
    }
}
