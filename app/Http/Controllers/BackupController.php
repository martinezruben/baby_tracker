<?php

namespace App\Http\Controllers;

use App\Support\Backup\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Respaldos (solo administradores): los automáticos guardados en el servidor y la descarga de uno nuevo. */
class BackupController extends Controller
{
    public function index(BackupService $backups): Response
    {
        return Inertia::render('Ajustes/Respaldos', [
            'backups' => $backups->stored(),
            'keep' => (int) config('backup.keep'),
            'time' => config('backup.time'),
            'auto_files' => (bool) config('backup.auto_include_files'),
        ]);
    }

    /** Crea un respaldo y lo deja guardado en el servidor. */
    public function store(Request $request, BackupService $backups): RedirectResponse
    {
        $withFiles = $request->boolean('files');

        try {
            $backups->create($backups->path($name = $backups->newName($withFiles)), $withFiles);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['backup' => trans('ui.respaldos.errors.failed')]);
        }

        $backups->prune((int) config('backup.keep'));

        return back()->with('status', trans('ui.respaldos.created', ['name' => $name]));
    }

    /** Crea un respaldo nuevo y lo descarga (no queda guardado en el servidor). */
    public function export(Request $request, BackupService $backups): BinaryFileResponse|RedirectResponse
    {
        $withFiles = $request->boolean('files');
        $path = storage_path('app/private/tmp/'.$backups->newName($withFiles));

        try {
            $backups->create($path, $withFiles);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['backup' => trans('ui.respaldos.errors.failed')]);
        }

        return response()->download($path, basename($path), ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    public function download(string $name, BackupService $backups): BinaryFileResponse
    {
        abort_unless(BackupService::isValidName($name) && Storage::disk('local')->exists(BackupService::DIR.'/'.$name), 404);

        return response()->download($backups->path($name), $name, ['Content-Type' => 'application/zip']);
    }

    public function destroy(string $name): RedirectResponse
    {
        abort_unless(BackupService::isValidName($name), 404);
        Storage::disk('local')->delete(BackupService::DIR.'/'.$name);

        return back()->with('status', trans('ui.respaldos.deleted'));
    }
}
