<?php

namespace App\Models\Concerns;

use App\Models\Baby;
use App\Models\Caretaker;
use App\Models\Family;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBaby
{
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }

    public function caretaker(): BelongsTo
    {
        return $this->belongsTo(Caretaker::class);
    }
}
