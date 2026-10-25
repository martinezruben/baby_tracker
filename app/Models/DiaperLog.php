<?php

namespace App\Models;

use App\Enums\DiaperType;
use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiaperLog extends Model
{
    use BelongsToBaby, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'type' => DiaperType::class,
            'blowout' => 'boolean',
            'cream_applied' => 'boolean',
        ];
    }
}
