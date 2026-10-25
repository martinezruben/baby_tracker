<?php

namespace App\Http\Controllers;

use App\Models\Baby;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BabySelectionController extends Controller
{
    public function store(Request $request, Baby $baby): RedirectResponse
    {
        abort_unless($baby->family_id === $request->user()->family_id, 404);

        $request->session()->put('baby_id', $baby->id);

        return back();
    }
}
