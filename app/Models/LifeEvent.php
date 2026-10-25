<?php

namespace App\Models;

use App\Enums\LifeEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LifeEvent extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => LifeEventType::class,
            'occurred_on' => 'date',
        ];
    }

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('taken_at');
    }
}
