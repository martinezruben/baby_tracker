<?php

namespace App\Models;

use App\Enums\CaretakerRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caretaker extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'role' => CaretakerRole::class,
            'is_active' => 'boolean',
            'pin' => 'hashed',
        ];
    }

    protected $hidden = ['pin'];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
