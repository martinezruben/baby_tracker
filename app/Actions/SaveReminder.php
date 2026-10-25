<?php

namespace App\Actions;

use App\Enums\NotificationEventType;
use App\Models\Caretaker;
use App\Models\NotificationRule;
use App\Services\Notifications\NotificationScheduler;
use Illuminate\Support\Facades\DB;

class SaveReminder
{
    public function __construct(private NotificationScheduler $scheduler) {}

    /**
     * Crea o actualiza un recordatorio suelto y reprograma sus avisos.
     *
     * @param  array<string, mixed>  $data  campos de la regla (event_at en UTC)
     * @param  array<int, int>  $recipientIds
     */
    public function handle(Caretaker $by, array $data, array $recipientIds, ?NotificationRule $rule = null): NotificationRule
    {
        return DB::transaction(function () use ($by, $data, $recipientIds, $rule) {
            if ($rule) {
                $this->scheduler->cancelAll($rule);
                $rule->update($data);
            } else {
                $rule = NotificationRule::create($data + [
                    'family_id' => $by->family_id,
                    'created_by' => $by->id,
                    'event_type' => NotificationEventType::Custom,
                    'channels' => ['push'],
                    'is_active' => true,
                ]);
            }

            $rule->recipients()->sync($recipientIds);

            if ($rule->is_active) {
                $this->scheduler->schedule($rule, $rule->event_at);
            }

            return $rule;
        });
    }
}
