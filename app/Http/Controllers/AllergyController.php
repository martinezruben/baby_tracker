<?php

namespace App\Http\Controllers;

use App\Enums\AllergenType;
use App\Models\BabyAllergen;
use App\Support\ActiveBaby;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Alergias del bebé activo (alimentos, medicinas, ambiente u otras). */
class AllergyController extends Controller
{
    public function index(Request $request): Response
    {
        $baby = ActiveBaby::for($request);

        return Inertia::render('Alergias', [
            'baby' => $baby->only(['id', 'first_name']),
            'types' => array_column(AllergenType::cases(), 'value'),
            'allergies' => BabyAllergen::where('baby_id', $baby->id)->orderBy('name')
                ->get(['id', 'name', 'type', 'reaction_description', 'notes'])
                ->map(fn (BabyAllergen $a) => ['type' => $a->type->value] + $a->only(['id', 'name', 'reaction_description', 'notes']))->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $baby = ActiveBaby::for($request);

        BabyAllergen::create($this->validated($request) + [
            'family_id' => $baby->family_id, 'baby_id' => $baby->id, 'caretaker_id' => $request->user()->id,
        ]);

        return back()->with('status', trans('ui.alergias.saved'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->find($request, $id)->update($this->validated($request));

        return back()->with('status', trans('ui.alergias.saved'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $allergy = $this->find($request, $id);
        $allergy->delete();

        return back()->with('undo', [
            'message' => trans('ui.alergias.deleted'),
            'url' => route('allergies.restore', $allergy->id),
            'method' => 'post',
        ]);
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        BabyAllergen::onlyTrashed()->where('family_id', $request->user()->family_id)->findOrFail($id)->restore();

        return back()->with('status', trans('ui.alergias.restored'));
    }

    private function find(Request $request, int $id): BabyAllergen
    {
        return BabyAllergen::where('family_id', $request->user()->family_id)
            ->where('baby_id', ActiveBaby::for($request)->id)->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(AllergenType::class)],
            'reaction_description' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], trans('ui.validation'), trans('ui.alergias.fields'));
    }
}
