<?php

namespace App\Models;

use App\Enums\MilestoneCategory;
use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends Model
{
    use BelongsToBaby, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'category' => MilestoneCategory::class,
        ];
    }
}
