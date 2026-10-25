<?php

namespace App\Models;

use App\Enums\DoseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionDose extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DoseStatus::class,
            'scheduled_at' => 'datetime',
            'given_at' => 'datetime',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medicineLog(): BelongsTo
    {
        return $this->belongsTo(MedicineLog::class);
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(Caretaker::class, 'given_by');
    }
}
