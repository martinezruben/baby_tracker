<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppointmentFileController extends Controller
{
    public const MAX_FILES = 10;

    public const MAX_KB = 10240;

    /** Tipos de documento para filtrar después. */
    public const CATEGORIES = ['prescription', 'exam', 'report', 'other'];

    public const MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    public function store(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.self::MAX_KB, 'mimetypes:'.implode(',', self::MIMES)],
            // Una descripción y un tipo por archivo, en el mismo orden que `files`.
            'descriptions' => ['nullable', 'array'],
            'descriptions.*' => ['nullable', 'string', 'max:500'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['nullable', Rule::in(self::CATEGORIES)],
        ], trans('ui.citas.file_errors'));

        $files = $request->file('files');

        if ($appointment->attachments()->count() + count($files) > self::MAX_FILES) {
            throw ValidationException::withMessages(['files' => trans('ui.citas.file_errors.too_many', ['max' => self::MAX_FILES])]);
        }

        foreach (array_values($files) as $i => $file) {
            $appointment->attachments()->create([
                'family_id' => $appointment->family_id,
                'uploaded_by' => $request->user()->id,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
                'category' => $request->input("categories.{$i}") ?: null,
                'description' => trim((string) $request->input("descriptions.{$i}")) ?: null,
                // Disco privado: nunca se sirve por URL pública, solo por esta ruta con sesión.
                'path' => $file->store("appointments/{$appointment->family_id}/{$appointment->id}", 'local'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return back()->with('status', trans('ui.citas.files_saved'));
    }

    /** Corregir la descripción o el tipo de un archivo ya subido. */
    public function update(Request $request, Appointment $appointment, AppointmentAttachment $attachment): RedirectResponse
    {
        $this->authorizeAttachment($request, $appointment, $attachment);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
        ], trans('ui.citas.file_errors'));

        $attachment->update(['description' => trim((string) ($data['description'] ?? '')) ?: null, 'category' => $data['category'] ?? null]);

        return back()->with('status', trans('ui.citas.file_updated'));
    }

    public function show(Request $request, Appointment $appointment, AppointmentAttachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($request, $appointment, $attachment);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ], 'inline');
    }

    public function destroy(Request $request, Appointment $appointment, AppointmentAttachment $attachment): RedirectResponse
    {
        $this->authorizeAttachment($request, $appointment, $attachment);

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', trans('ui.citas.file_deleted'));
    }

    private function authorizeAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless($appointment->family_id === $request->user()->family_id, 404);
    }

    private function authorizeAttachment(Request $request, Appointment $appointment, AppointmentAttachment $attachment): void
    {
        $this->authorizeAppointment($request, $appointment);
        abort_unless($attachment->appointment_id === $appointment->id, 404);
    }
}
