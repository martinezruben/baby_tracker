<?php

namespace App\Support;

use App\Models\Baby;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ActiveBaby
{
    /**
     * Los bebés de la familia (del mayor al menor), cargados **una sola vez por petición**: los usan el
     * middleware de Inertia, el selector de bebé y cada controlador. Cada bebé trae ya su familia.
     *
     * @return Collection<int, Baby>
     */
    public static function all(Request $request): Collection
    {
        return $request->attributes->get('family_babies') ?? tap(
            ($family = $request->user()->family)->babies()->orderBy('birth_date')->get()->each(fn (Baby $b) => $b->setRelation('family', $family)),
            fn ($babies) => $request->attributes->set('family_babies', $babies),
        );
    }

    /** El bebé elegido en la sesión (si es de la familia) o, si no, el primero. */
    public static function for(Request $request): Baby
    {
        $babies = self::all($request);

        return $babies->firstWhere('id', $request->session()->get('baby_id')) ?? $babies->first() ?? abort(404);
    }
}
