<?php

namespace App\Models;

use App\Enums\AllergenType;
use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BabyAllergen extends Model
{
    use BelongsToBaby, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => AllergenType::class,
        ];
    }
}
