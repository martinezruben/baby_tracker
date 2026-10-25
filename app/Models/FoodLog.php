<?php

namespace App\Models;

use App\Enums\FoodEnjoyment;
use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FoodLog extends Model
{
    use BelongsToBaby, SoftDeletes;

    protected $guarded = ['id'];

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'amount' => 'decimal:2',
            'enjoyment' => FoodEnjoyment::class,
            'had_reaction' => 'boolean',
        ];
    }
}
