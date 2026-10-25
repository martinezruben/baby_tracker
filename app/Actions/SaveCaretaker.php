<?php

namespace App\Actions;

use App\Enums\CaretakerRole;
use App\Enums\NotificationEventType;
use App\Models\Caretaker;
use App\Models\Family;
use App\Models\NotificationRule;
use App\Services\Notifications\NotificationScheduler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveCaretaker
{
    /** Tipos de regla que avisan a todos los cuidadores (los recordatorios sueltos eligen sus destinatarios). */
    private const SHARED_RULES = [NotificationEventType::Appointment, NotificationEventType::Medicine, NotificationEventType::Vaccine];

    public function __construct(private NotificationScheduler $scheduler) {}

    /** @param  array{name: string, type?: ?string, role: string, badge_color?: ?string, language?: ?string, pin?: ?string}  $data */
    public function create(Family $family, array $data): Caretaker
    {
        return DB::transaction(function () use ($family, $data) {
            $caretaker = $family->caretakers()->create([
                'login_id' => $this->loginId($family, $data['name']),
                'name' => trim($data['name']),
                'type' => $data['type'] ?? null,
                'role' => $data['role'],
                'badge_color' => $data['badge_color'] ?? null,
                'language' => $data['language'] ?? null,
                'pin' => $data['pin'],
                'is_active' => true,
            ]);

            $this->joinSharedRules($caretaker);

            return $caretaker;
        });
    }

    /** @param  array{name: string, type?: ?string, role: string, badge_color?: ?string, language?: ?string, pin?: ?string}  $data */
    public function update(Caretaker $caretaker, array $data): Caretaker
    {
        return DB::transaction(function () use ($caretaker, $data) {
            if ($caretaker->role === CaretakerRole::Admin && $data['role'] !== CaretakerRole::Admin->value) {
                $this->ensureAnotherAdmin($caretaker);
            }

            $caretaker->fill([
                'name' => trim($data['name']),
                'type' => $data['type'] ?? null,
                'role' => $data['role'],
                'badge_color' => $data['badge_color'] ?? null,
                'language' => $data['language'] ?? null,
            ]);

            if (! empty($data['pin'])) {
                $caretaker->pin = $data['pin']; // cifrado por el cast
            }

            $caretaker->save();

            return $caretaker;
        });
    }

    /** Dar de baja: no se borra (sus registros se conservan), pero deja de entrar y de recibir avisos. */
    public function deactivate(Caretaker $caretaker, Caretaker $by): void
    {
        if ($caretaker->is($by)) {
            throw ValidationException::withMessages(['caretaker' => trans('ui.ajustes.errors.self')]);
        }

        if ($caretaker->role === CaretakerRole::Admin) {
            $this->ensureAnotherAdmin($caretaker);
        }

        DB::transaction(function () use ($caretaker) {
            $caretaker->update(['is_active' => false]);
            $caretaker->pushSubscriptions()->delete();
            $this->scheduler->removeRecipient($caretaker);

            // Si las sesiones están en la base de datos, se cierran las suyas.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $caretaker->id)->delete();
            }
        });
    }

    public function reactivate(Caretaker $caretaker): void
    {
        DB::transaction(function () use ($caretaker) {
            $caretaker->update(['is_active' => true]);
            $this->joinSharedRules($caretaker);
        });
    }

    /** Un cuidador nuevo (o reactivado) recibe también los avisos de lo que ya está programado. */
    private function joinSharedRules(Caretaker $caretaker): void
    {
        NotificationRule::query()
            ->where('family_id', $caretaker->family_id)
            ->where('is_active', true)
            ->whereIn('event_type', self::SHARED_RULES)
            ->get()
            ->each(fn (NotificationRule $rule) => $this->scheduler->addRecipient($rule, $caretaker));
    }

    private function ensureAnotherAdmin(Caretaker $caretaker): void
    {
        $others = Caretaker::where('family_id', $caretaker->family_id)
            ->where('role', CaretakerRole::Admin)->where('is_active', true)->whereKeyNot($caretaker->id)->exists();

        if (! $others) {
            throw ValidationException::withMessages(['role' => trans('ui.ajustes.errors.last_admin')]);
        }
    }

    /** Identificador de acceso corto y único dentro de la familia. */
    private function loginId(Family $family, string $name): string
    {
        $base = Str::of(Str::slug($name))->limit(5, '')->toString() ?: 'user';

        do {
            $candidate = $base.random_int(100, 999);
        } while ($family->caretakers()->withTrashed()->where('login_id', $candidate)->exists());

        return $candidate;
    }
}
