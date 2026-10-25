<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaccineDose extends Model
{
    protected $guarded = ['id'];

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }
}
