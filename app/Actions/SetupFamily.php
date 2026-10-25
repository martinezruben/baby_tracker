<?php

namespace App\Actions;

use App\Enums\CaretakerRole;
use App\Models\Caretaker;
use App\Models\Family;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SetupFamily
{
    /**
     * Crea la familia, sus ajustes, el primer cuidador (administrador) y el primer bebé.
     *
     * @param  array{family_name: string, slug?: ?string, language: string, admin_name: string, pin: string, baby_first_name: string, baby_birth_date: string, baby_gender?: ?string, feed_warning_minutes?: ?int, diaper_warning_minutes?: ?int}  $data
     */
    public function handle(array $data): Caretaker
    {
        return DB::transaction(function () use ($data) {
            $family = Family::create([
                'name' => $data['family_name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['family_name']),
                'setup_stage' => 3,
            ]);

            $family->settings()->create(['language' => $data['language']]);

            $admin = $family->caretakers()->create([
                'login_id' => 'admin',
                'name' => $data['admin_name'],
                'role' => CaretakerRole::Admin,
                'pin' => $data['pin'],
                'badge_color' => '#8B6340',
                'language' => $data['language'],
            ]);

            $family->babies()->create([
                'first_name' => $data['baby_first_name'],
                'birth_date' => $data['baby_birth_date'],
                'gender' => $data['baby_gender'] ?? null,
                'feed_warning_minutes' => $data['feed_warning_minutes'] ?? 180,
                'diaper_warning_minutes' => $data['diaper_warning_minutes'] ?? 180,
            ]);

            return $admin;
        });
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'familia';
        $slug = $base;
        $n = 2;

        while (Family::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
