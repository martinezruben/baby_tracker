<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Food extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_common_allergen' => 'boolean',
        ];
    }

    protected $table = 'foods';

    public function logs(): HasMany
    {
        return $this->hasMany(FoodLog::class);
    }
}
