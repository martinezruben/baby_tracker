<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BreastMilkAdjustment extends Model
{
    use BelongsToBaby, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }
}
