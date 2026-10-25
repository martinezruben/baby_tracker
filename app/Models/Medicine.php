<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'typical_dose' => 'decimal:2',
            'is_active' => 'boolean',
            'is_supplement' => 'boolean',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MedicineLog::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_medicine');
    }
}
