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
            new MedicineActivity,
            new MeasurementActivity,
            new NoteActivity,
        ])->keyBy(fn (ActivityType $t) => $t->slug())->all();
    }

    public static function find(string $slug): ?ActivityType
    {
        return self::all()[$slug] ?? null;
    }

    public static function findOrFail(string $slug): ActivityType
    {
        return self::find($slug) ?? abort(404);
    }
}
