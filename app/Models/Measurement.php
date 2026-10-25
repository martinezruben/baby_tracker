<?php

namespace App\Models;

use App\Enums\MeasurementType;
use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Measurement extends Model
{
    use BelongsToBaby, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'measured_on' => 'date',
            'type' => MeasurementType::class,
            'value' => 'decimal:2',
        ];
    }
}
