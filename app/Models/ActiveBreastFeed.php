<?php

namespace App\Models;

use App\Enums\BreastSide;
use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;

class ActiveBreastFeed extends Model
{
    use BelongsToBaby;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ticked_at' => 'datetime',
            'is_paused' => 'boolean',
            'active_side' => BreastSide::class,
        ];
    }
}
