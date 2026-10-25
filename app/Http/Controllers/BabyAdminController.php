<?php

namespace App\Http\Controllers;

use App\Actions\SyncVaccineReminders;
use App\Enums\Gender;
use App\Models\Baby;
use App\Models\NotificationRule;
use App\Services\Notifications\NotificationScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BabyAdminController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Ajustes/Bebes', [
            'babies' => $request->user()->family->babies()->orderBy('birth_date')->get()->map(fn (Baby $b) => [
                'id' => $b->id,
                'first_name' => $b->first_name,
                'last_name' => $b->last_name,
                'birth_date' => $b->birth_date->toDateString(),
                'gender' => $b->gender?->value,
                'feed_warning_minutes' => $b->feed_warning_minutes,
                'diaper_warning_minutes' => $b->diaper_warning_minutes,
            ])->all(),
            'today' => now($request->user()->family->settings->timezone)->toDateString(),
        ]);
    }

    public function store(Request $request, SyncVaccineReminders $sync): RedirectResponse
    {
        $baby = $request->user()->family->babies()->create($this->validated($request));

        $sync->handle($baby);

        return back()->with('status', trans('ui.ajustes.baby_added'));
    }

    public function update(Request $request, Baby $baby, SyncVaccineReminders $sync): RedirectResponse
    {
        $this->authorizeBaby($request, $baby);

        $baby->update($this->validated($request));
        $sync->handle($baby); // la edad cambia cuándo toca cada vacuna

        return back()->with('status', trans('ui.ajustes.saved'));
    }

    /** Se da de baja (sus registros se conservan) y se apagan sus recordatorios. */
    public function destroy(Request $request, Baby $baby, NotificationScheduler $scheduler): RedirectResponse
    {
        $this->authorizeBaby($request, $baby);

        if ($request->user()->family->babies()->count() <= 1) {
            throw ValidationException::withMessages(['baby' => trans('ui.ajustes.errors.last_baby')]);
        }

        NotificationRule::where('baby_id', $baby->id)->where('is_active', true)->get()->each(function (NotificationRule $rule) use ($scheduler) {
            $scheduler->cancelAll($rule);
            $rule->update(['is_active' => false]);
        });

        $baby->delete();

        if ($request->session()->get('baby_id') === $baby->id) {
            $request->session()->forget('baby_id');
        }

        return back()->with('status', trans('ui.ajustes.baby_deleted'));
    }

    private function authorizeBaby(Request $request, Baby $baby): void
    {
        abort_unless($baby->family_id === $request->user()->family_id, 404);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $today = now($request->user()->family->settings->timezone)->toDateString();

        return $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'feed_warning_minutes' => ['required', 'integer', 'between:15,720'],
            'diaper_warning_minutes' => ['required', 'integer', 'between:15,720'],
        ], trans('ui.validation'), trans('ui.ajustes.fields'));
    }
}
