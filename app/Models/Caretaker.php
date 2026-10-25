<?php

namespace App\Models;

use App\Enums\CaretakerRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Caretaker extends Authenticatable
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

    // Los cuidadores no usan "recordarme": la sesión de la familia dura lo que dura la sesión.
    protected $rememberTokenName = '';

    public function getAuthPassword(): string
    {
        return (string) $this->pin;
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
