<?php

namespace App\Http\Controllers;

use App\Support\ActiveBaby;
use App\Support\Timeline;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogController extends Controller
{
    public function __invoke(Request $request, Timeline $timeline): Response
    {
        $type = in_array($request->query('tipo'), $timeline->types(), true) ? $request->query('tipo') : null;
        $before = is_string($request->query('antes')) ? $request->query('antes') : null;

        $page = $timeline->page(ActiveBaby::for($request), $type, $before);

        return Inertia::render('Bitacora', [
            'tipo' => $type,
            'types' => $timeline->types(),
            // Las páginas siguientes se suman a la lista ya cargada (merge de Inertia).
            'entries' => Inertia::merge($page['entries']),
            'next' => $page['next'],
        ]);
    }
}
