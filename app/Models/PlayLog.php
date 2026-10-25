<?php

namespace App\Models;

use App\Enums\PlayType;
use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayLog extends Model
{
    use BelongsToBaby, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'type' => PlayType::class,
        ];
    }
}
