<?php

namespace App\Http\Controllers;

use App\Http\Requests\PinRules;
use App\Support\Backup\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** Restaurar un respaldo en una instalación nueva (solo existe mientras no haya familia, como el asistente). */
class RestoreController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Restaurar');
    }

    public function store(Request $request, BackupService $backups): RedirectResponse
    {
        $data = $request->validate([
            'backup' => ['required', 'file', 'mimes:zip'],
            'pin' => PinRules::newPin(),
        ], trans('ui.validation'), trans('ui.respaldos.fields'));

        try {
            $result = $backups->restore($data['backup']->getRealPath(), $data['pin']);
        } catch (Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return redirect()->route('login')->with('restored', $result);
    }
}
