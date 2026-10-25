<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBaby;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vaccination extends Model
{
    use BelongsToBaby, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['administered_at' => 'datetime'];
    }

    public function dose(): BelongsTo
    {
        return $this->belongsTo(VaccineDose::class, 'vaccine_dose_id');
    }
}
