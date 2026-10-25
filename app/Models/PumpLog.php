<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PumpLog extends Model
{
    use BelongsToBaby, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'left_amount' => 'decimal:2',
            'right_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }
}
