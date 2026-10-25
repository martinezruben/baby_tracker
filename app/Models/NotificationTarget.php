<?php

namespace App\Models;

use App\Enums\TargetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTarget extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TargetStatus::class,
            'next_send_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(NotificationOccurrence::class, 'notification_occurrence_id');
    }

    public function caretaker(): BelongsTo
    {
        return $this->belongsTo(Caretaker::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
