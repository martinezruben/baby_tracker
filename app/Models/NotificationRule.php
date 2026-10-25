<?php

namespace App\Models;

use App\Enums\AckScope;
use App\Enums\NotificationEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationRule extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event_type' => NotificationEventType::class,
            'ack_scope' => AckScope::class,
            'offsets_minutes' => 'array',
            'snooze_options' => 'array',
            'channels' => 'array',
            'requires_ack' => 'boolean',
            'respect_quiet_hours' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Caretaker::class, 'notification_rule_caretaker');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(NotificationOccurrence::class);
    }
}
