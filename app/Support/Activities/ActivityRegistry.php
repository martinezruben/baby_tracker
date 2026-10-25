<?php

namespace App\Support\Activities;

class ActivityRegistry
{
    /** @return array<string, ActivityType> por slug, en el orden en que se muestran */
    public static function all(): array
    {
        static $types;

        return $types ??= collect([
            new BottleActivity,
            new DiaperActivity,
            new SleepActivity,
            new SolidsActivity,
            new PumpActivity,
            new BathActivity,
            new PlayActivity,
            new MedicineActivity,
            new MeasurementActivity,
            new NoteActivity,
            new MoodActivity,
        ])->keyBy(fn (ActivityType $t) => $t->slug())->all();
    }

    public static function find(string $slug): ?ActivityType
    {
        return self::all()[$slug] ?? null;
    }

    /** Tipo cuyos registros se pueden corregir: los del registro más la lactancia con cronómetro. */
    public static function editable(string $slug): ActivityType
    {
        return $slug === 'pecho' ? new BreastActivity : self::findOrFail($slug);
    }

    public static function findOrFail(string $slug): ActivityType
    {
        return self::find($slug) ?? abort(404);
    }
}
