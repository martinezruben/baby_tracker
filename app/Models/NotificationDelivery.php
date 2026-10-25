<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(NotificationTarget::class, 'notification_target_id');
    }
}
