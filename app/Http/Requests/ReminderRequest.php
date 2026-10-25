<?php

namespace App\Http\Requests;

use App\Enums\AckScope;
use App\Models\Baby;
use App\Models\Caretaker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReminderRequest extends FormRequest
{
    public const OFFSETS = [0, -5, -10, -15, -30, -60, -120, -1440, -10080];

    public const SNOOZE = [5, 10, 15, 30, 60, 120];

    public function rules(): array
    {
        $familyId = $this->user()->family_id;

        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:300'],
            'event_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'baby_id' => ['nullable', 'integer', Rule::exists(Baby::class, 'id')->where('family_id', $familyId)->whereNull('deleted_at')],
            'offsets_minutes' => ['required', 'array', 'min:1'],
            'offsets_minutes.*' => ['integer', Rule::in(self::OFFSETS), 'distinct'],
            'repeat_every_minutes' => ['nullable', 'integer', 'between:1,1440'],
            'max_repeats' => ['nullable', 'integer', 'between:1,50'],
            'requires_ack' => ['required', 'boolean'],
            'ack_scope' => ['required', Rule::enum(AckScope::class)],
            'snooze_options' => ['required', 'array', 'min:1'],
            'snooze_options.*' => ['integer', Rule::in(self::SNOOZE), 'distinct'],
            'quiet_start' => ['nullable', 'date_format:H:i', 'required_with:quiet_end'],
            'quiet_end' => ['nullable', 'date_format:H:i', 'required_with:quiet_start'],
            'respect_quiet_hours' => ['required', 'boolean'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['integer', Rule::exists(Caretaker::class, 'id')->where('family_id', $familyId)->where('is_active', true)->whereNull('deleted_at')],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            // La fecha se escribe en la hora de la familia, no en la del servidor.
            if (! $validator->errors()->has('event_at') && $this->filled('event_at')
                && ! CarbonImmutable::parse($this->input('event_at'), $this->familyTimezone())->isFuture()) {
                $validator->errors()->add('event_at', trans('ui.validation.after'));
            }

            if ($this->boolean('requires_ack') && ! $this->filled('repeat_every_minutes')) {
                $validator->errors()->add('repeat_every_minutes', trans('ui.reminders.errors.repeat_required_for_ack'));
            }

            if (! $this->boolean('requires_ack') && $this->filled('repeat_every_minutes') && ! $this->filled('max_repeats')) {
                $validator->errors()->add('max_repeats', trans('ui.reminders.errors.max_repeats_required'));
            }
        }];
    }

    public function familyTimezone(): string
    {
        return $this->user()->family->settings->timezone ?? config('app.timezone');
    }

    public function messages(): array
    {
        return trans('ui.validation');
    }

    public function attributes(): array
    {
        return trans('ui.reminders.fields');
    }

    /** @return array<string, mixed> campos de la regla, con el evento ya en UTC */
    public function ruleData(string $timezone): array
    {
        $data = $this->validated();

        return [
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'event_at' => CarbonImmutable::parse($data['event_at'], $timezone)->utc(),
            'baby_id' => $data['baby_id'] ?? null,
            'offsets_minutes' => collect($data['offsets_minutes'])->map(fn ($m) => (int) $m)->sort()->values()->all(),
            'requires_ack' => $data['requires_ack'],
            'repeat_every_minutes' => $data['repeat_every_minutes'] ?? null,
            // Si insiste hasta confirmar no hay límite propio: manda el tope de seguridad.
            'max_repeats' => $data['requires_ack'] || empty($data['repeat_every_minutes']) ? null : $data['max_repeats'],
            'ack_scope' => $data['ack_scope'],
            'snooze_options' => collect($data['snooze_options'])->map(fn ($m) => (int) $m)->sort()->values()->all(),
            'quiet_start' => $data['quiet_start'] ?? null,
            'quiet_end' => $data['quiet_end'] ?? null,
            'respect_quiet_hours' => $data['respect_quiet_hours'],
        ];
    }

    /** @return array<int, int> */
    public function recipientIds(): array
    {
        return array_map('intval', $this->validated('recipients'));
    }
}
