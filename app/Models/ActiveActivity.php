<?php

namespace App\Models;

use App\Enums\PlayType;
use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;

class ActiveActivity extends Model
{
    use BelongsToBaby;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'is_paused' => 'boolean',
            'play_type' => PlayType::class,
        ];
    }
}
