<?php

namespace App\Support;

use App\Models\Baby;
use Illuminate\Http\Request;

class ActiveBaby
{
    /** El bebé elegido en la sesión (si es de la familia) o, si no, el primero. */
    public static function for(Request $request): Baby
    {
        $family = $request->user()->family;

        return $family->babies()->find($request->session()->get('baby_id'))
            ?? $family->babies()->orderBy('birth_date')->firstOrFail();
    }
}
