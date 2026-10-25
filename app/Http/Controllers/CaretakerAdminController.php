<?php

namespace App\Http\Controllers;

use App\Actions\SaveCaretaker;
use App\Enums\CaretakerRole;
use App\Http\Requests\PinRules;
use App\Models\Caretaker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CaretakerAdminController extends Controller
{
    public const COLORS = ['#8B6340', '#1D6FB0', '#4C46B6', '#A63A86', '#B4532F', '#A15C07', '#4D7A12', '#3A5288'];

    public function index(Request $request): Response
    {
        return Inertia::render('Ajustes/Cuidadores', [
            'caretakers' => $request->user()->family->caretakers()->orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn (Caretaker $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'type' => $c->type,
                    'role' => $c->role->value,
                    'badge_color' => $c->badge_color,
                    'language' => $c->language,
                    'is_active' => $c->is_active,
                    'is_me' => $c->id === $request->user()->id,
                ])->all(),
            'colors' => self::COLORS,
            'languages' => SettingsController::LANGUAGES,
        ]);
    }

    public function store(Request $request, SaveCaretaker $save): RedirectResponse
    {
        $data = $request->validate($this->rules(pinRequired: true), trans('ui.validation'), trans('ui.ajustes.fields'));

        $save->create($request->user()->family, $data);

        return back()->with('status', trans('ui.ajustes.caretaker_added'));
    }

    public function update(Request $request, Caretaker $caretaker, SaveCaretaker $save): RedirectResponse
    {
        $this->authorizeCaretaker($request, $caretaker);
        $data = $request->validate($this->rules(pinRequired: false), trans('ui.validation'), trans('ui.ajustes.fields'));

        $save->update($caretaker, $data);

        return back()->with('status', trans('ui.ajustes.saved'));
    }

    public function deactivate(Request $request, Caretaker $caretaker, SaveCaretaker $save): RedirectResponse
    {
        $this->authorizeCaretaker($request, $caretaker);
        $save->deactivate($caretaker, $request->user());

        return back()->with('status', trans('ui.ajustes.caretaker_deactivated'));
    }

    public function reactivate(Request $request, Caretaker $caretaker, SaveCaretaker $save): RedirectResponse
    {
        $this->authorizeCaretaker($request, $caretaker);
        $save->reactivate($caretaker);

        return back()->with('status', trans('ui.ajustes.caretaker_reactivated'));
    }

    private function authorizeCaretaker(Request $request, Caretaker $caretaker): void
    {
        abort_unless($caretaker->family_id === $request->user()->family_id, 404);
    }

    /** @return array<string, mixed> */
    private function rules(bool $pinRequired): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'type' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::enum(CaretakerRole::class)],
            'badge_color' => ['nullable', Rule::in(self::COLORS)],
            'language' => ['nullable', Rule::in(SettingsController::LANGUAGES)],
            'pin' => $pinRequired ? PinRules::newPin() : ['nullable', ...array_slice(PinRules::newPin(), 1)],
        ];
    }
}
