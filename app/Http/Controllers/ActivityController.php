<?php

namespace App\Http\Controllers;

use App\Models\FeedLog;
use App\Support\ActiveBaby;
use App\Support\Activities\ActivityContext;
use App\Support\Activities\ActivityRegistry;
use App\Support\FamilyClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function store(Request $request, string $type): RedirectResponse
    {
        $activity = ActivityRegistry::findOrFail($type);
        $context = $this->context($request);

        $data = $request->validate($activity->rules($context), trans('ui.validation'), trans('ui.registrar.fields'));

        return $this->saved($type, $activity->create($context, $data));
    }

    /** Corregir un registro ya guardado (desde la Bitácora). */
    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $activity = ActivityRegistry::editable($type);
        $context = $this->context($request);
        $log = $activity->query($context->baby->id)->where('family_id', $context->baby->family_id)->findOrFail($id);

        $data = $request->validate($activity->rules($context), trans('ui.validation'), trans('ui.registrar.fields'));
        $activity->update($context, $log, $data);

        return redirect()->route('log')->with('status', trans('ui.log.updated'));
    }

    public function repeat(Request $request, string $type): RedirectResponse
    {
        $activity = ActivityRegistry::findOrFail($type);
        abort_unless($activity->repeatable(), 404);

        $context = $this->context($request);
        $last = $activity->query($context->baby->id)->latest($activity->timeColumn())->first();
        abort_unless($last, 404);

        return $this->saved($type, $activity->repeat($context, $last));
    }

    /** Deshacer: el registro se borra de forma lógica. */
    public function destroy(Request $request, string $type, int $id): RedirectResponse
    {
        // "pecho" no es un tipo de formulario (tiene su cronómetro), pero su registro también se deshace.
        $model = $type === 'pecho' ? FeedLog::class : ActivityRegistry::findOrFail($type)->model();

        $log = $model::query()
            ->where('family_id', $request->user()->family_id)
            ->findOrFail($id);

        $log->delete();

        // Desde la Bitácora se avisa con "Deshacer" (restaurar); al deshacer un registro recién
        // guardado basta con un aviso.
        if ($request->query('desde') === 'bitacora') {
            return back()->with('undo', [
                'message' => trans('ui.log.deleted'),
                'url' => route('records.restore', ['type' => $type, 'id' => $log->id]),
                'method' => 'post',
            ]);
        }

        return back()->with('status', trans('ui.registrar.undone'));
    }

    public function restore(Request $request, string $type, int $id): RedirectResponse
    {
        $model = $type === 'pecho' ? FeedLog::class : ActivityRegistry::findOrFail($type)->model();

        $model::onlyTrashed()
            ->where('family_id', $request->user()->family_id)
            ->findOrFail($id)
            ->restore();

        return back()->with('status', trans('ui.log.restored'));
    }

    private function context(Request $request): ActivityContext
    {
        $user = $request->user();

        return new ActivityContext(ActiveBaby::for($request), $user, FamilyClock::for($user->family));
    }

    private function saved(string $type, Model $log): RedirectResponse
    {
        return redirect()->route('inicio')->with('undo', [
            'message' => trans("ui.registrar.saved.{$type}"),
            'url' => route('records.destroy', ['type' => $type, 'id' => $log->id]),
        ]);
    }
}
