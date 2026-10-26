<?php

namespace App\Http\Controllers;

use App\Models\AppointmentAttachment;
use App\Support\ActiveBaby;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Documentos de las citas del bebé (recetas, exámenes, informes) con búsqueda por texto y tipo. */
class DocumentController extends Controller
{
    public const LIMIT = 100;

    public function __invoke(Request $request): Response
    {
        $baby = ActiveBaby::for($request);
        $q = trim((string) $request->query('q'));
        $category = in_array($request->query('tipo'), AppointmentFileController::CATEGORIES, true) ? $request->query('tipo') : null;

        $query = AppointmentAttachment::query()
            ->where('appointment_attachments.family_id', $baby->family_id)
            ->whereHas('appointment', fn ($a) => $a->where('baby_id', $baby->id))
            ->with('appointment:id,reason,doctor_name,scheduled_at')
            ->when($category, fn ($w) => $w->where('category', $category))
            ->when($q !== '', function ($w) use ($q) {
                // Cada palabra debe aparecer en la descripción, el nombre del archivo o los datos de la cita.
                foreach (preg_split('/\s+/', mb_substr($q, 0, 100)) as $word) {
                    // "!" como carácter de escape: funciona igual en MySQL y SQLite (a diferencia de "\").
                    $like = '%'.strtr($word, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                    $w->where(fn ($x) => $x
                        ->whereRaw("description LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("original_name LIKE ? ESCAPE '!'", [$like])
                        ->orWhereHas('appointment', fn ($a) => $a->whereRaw("reason LIKE ? ESCAPE '!'", [$like])->orWhereRaw("doctor_name LIKE ? ESCAPE '!'", [$like])));
                }
            });

        $total = (clone $query)->count();

        $files = $query->join('appointments', 'appointments.id', '=', 'appointment_attachments.appointment_id')
            ->orderByDesc('appointments.scheduled_at')->orderByDesc('appointment_attachments.id')
            ->select('appointment_attachments.*')->limit(self::LIMIT)->get();

        return Inertia::render('Documentos', [
            'baby' => $baby->only(['id', 'first_name']),
            'q' => $q,
            'tipo' => $category,
            'categories' => AppointmentFileController::CATEGORIES,
            'total' => $total,
            'files' => $files->map(fn (AppointmentAttachment $f) => [
                'id' => $f->id,
                'name' => $f->original_name,
                'category' => $f->category,
                'description' => $f->description,
                'is_image' => $f->isImage(),
                'size' => $f->size,
                'url' => route('appointments.files.show', [$f->appointment_id, $f]),
                'appointment' => [
                    'id' => $f->appointment->id,
                    'reason' => $f->appointment->reason,
                    'doctor' => $f->appointment->doctor_name,
                    'at' => $f->appointment->scheduled_at->toIso8601String(),
                ],
            ])->all(),
        ]);
    }
}
