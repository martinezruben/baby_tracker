<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Baby extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
            'feed_timer_types' => 'array',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function activeBreastFeed(): HasOne
    {
        return $this->hasOne(ActiveBreastFeed::class);
    }

    public function activeActivity(): HasOne
    {
        return $this->hasOne(ActiveActivity::class);
    }

    public function sleepLogs(): HasMany
    {
        return $this->hasMany(SleepLog::class);
    }

    public function feedLogs(): HasMany
    {
        return $this->hasMany(FeedLog::class);
    }

    public function diaperLogs(): HasMany
    {
        return $this->hasMany(DiaperLog::class);
    }

    public function moodLogs(): HasMany
    {
        return $this->hasMany(MoodLog::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function pumpLogs(): HasMany
    {
        return $this->hasMany(PumpLog::class);
    }

    public function breastMilkAdjustments(): HasMany
    {
        return $this->hasMany(BreastMilkAdjustment::class);
    }

    public function playLogs(): HasMany
    {
        return $this->hasMany(PlayLog::class);
    }

    public function bathLogs(): HasMany
    {
        return $this->hasMany(BathLog::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function medicineLogs(): HasMany
    {
        return $this->hasMany(MedicineLog::class);
    }

    public function foodLogs(): HasMany
    {
        return $this->hasMany(FoodLog::class);
    }

    public function allergens(): HasMany
    {
        return $this->hasMany(BabyAllergen::class);
    }
}
