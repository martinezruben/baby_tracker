<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Support\FamilyClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRequest extends FormRequest
{
    public const VITALS = ['weight', 'height', 'head_circumference', 'temperature'];

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:160'],
            'doctor_name' => ['nullable', 'string', 'max:120'],
            'specialty' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:160'],
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'symptoms' => ['nullable', 'string', 'max:5000'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:scheduled_at'],
            'remind' => ['required', 'boolean'],
            'offsets_minutes' => ['required_if:remind,true', 'array'],
            'offsets_minutes.*' => ['integer', Rule::in(ReminderRequest::OFFSETS), 'distinct'],
            'vitals' => ['nullable', 'array'],
            'vitals.weight' => ['nullable', 'numeric', 'between:0.1,500'],
            'vitals.height' => ['nullable', 'numeric', 'between:1,300'],
            'vitals.head_circumference' => ['nullable', 'numeric', 'between:1,100'],
            'vitals.temperature' => ['nullable', 'numeric', 'between:25,45'],
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

    /** @return array<string, mixed> campos de la cita con las fechas en UTC */
    public function appointmentData(): array
    {
        $data = $this->validated();
        $clock = FamilyClock::for($this->user()->family);

        return [
            'reason' => $data['reason'],
            'doctor_name' => $data['doctor_name'] ?? null,
            'specialty' => $data['specialty'] ?? null,
            'location' => $data['location'] ?? null,
            'scheduled_at' => $clock->parse($data['scheduled_at']),
            'status' => $data['status'],
            'symptoms' => $data['symptoms'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'notes' => $data['notes'] ?? null,
            'follow_up_at' => isset($data['follow_up_at']) ? $clock->parse($data['follow_up_at']) : null,
            'remind' => $data['remind'],
            'offsets_minutes' => $data['offsets_minutes'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function vitals(): array
    {
        return collect(self::VITALS)->mapWithKeys(fn ($k) => [$k => $this->validated("vitals.{$k}")])->all();
    }
}
