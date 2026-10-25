<?php

namespace App\Models;

use App\Enums\OccurrenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationOccurrence extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'status' => OccurrenceStatus::class,
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(NotificationRule::class, 'notification_rule_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(NotificationTarget::class);
    }
}
