<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Family extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function babies(): HasMany
    {
        return $this->hasMany(Baby::class);
    }

    public function caretakers(): HasMany
    {
        return $this->hasMany(Caretaker::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(FamilySettings::class);
    }
}
