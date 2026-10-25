<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilySettings extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activity_visibility' => 'array',
            'sleep_locations' => 'array',
            'bath_types' => 'array',
            'nursery_mode' => 'array',
        ];
    }

    protected $table = 'family_settings';

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
