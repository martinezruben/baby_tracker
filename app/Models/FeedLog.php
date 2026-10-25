<?php

namespace App\Models;

use App\Enums\BreastSide;
use App\Enums\FeedType;
use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedLog extends Model
{
    use BelongsToBaby, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'type' => FeedType::class,
            'side' => BreastSide::class,
            'amount' => 'decimal:2',
        ];
    }
}
