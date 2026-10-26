<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Support\ContactPhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Directorio de la familia: pediatra, emergencias, familiares y cuidadores externos. */
class ContactController extends Controller
{
    private const PHONE = ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-.\s]{5,30}$/'];

    public function index(Request $request): Response
    {
        return Inertia::render('Contactos', [
            'contacts' => Contact::where('family_id', $request->user()->family_id)->orderBy('name')->get()
                ->map(fn (Contact $c) => $c->only(['id', 'name', 'role', 'phone', 'work_phone', 'email', 'instagram', 'address', 'notes']) + [
                    // `v` fuerza a recargar la foto cuando se cambia.
                    'photo_url' => $c->photo_path ? route('contacts.photo', $c->id).'?v='.$c->updated_at->timestamp : null,
                ])->all(),
        ]);
    }

    public function store(Request $request, ContactPhotoStorage $photos): RedirectResponse
    {
        $data = $this->validated($request);
        $familyId = $request->user()->family_id;

        // La foto se procesa antes de crear el contacto: si no es válida, no queda un contacto a medias.
        $photoPath = $data['photo'] ? $photos->store($data['photo'], $familyId) : null;
        Contact::create($data['fields'] + ['family_id' => $familyId, 'photo_path' => $photoPath]);

        return back()->with('status', trans('ui.contactos.saved'));
    }

    public function update(Request $request, int $id, ContactPhotoStorage $photos): RedirectResponse
    {
        $contact = $this->find($request, $id);
        $data = $this->validated($request);

        $fields = $data['fields'];
        if ($data['photo']) {
            $new = $photos->store($data['photo'], $contact->family_id);
            $photos->delete($contact->photo_path);
            $fields['photo_path'] = $new;
        } elseif ($request->boolean('remove_photo')) {
            $photos->delete($contact->photo_path);
            $fields['photo_path'] = null;
        }

        $contact->update($fields);

        return back()->with('status', trans('ui.contactos.saved'));
    }

    /** Se borra de forma lógica y la Snackbar ofrece deshacer (la foto se conserva para poder restaurar). */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $contact = $this->find($request, $id);
        $contact->delete();

        return back()->with('undo', [
            'message' => trans('ui.contactos.deleted'),
            'url' => route('contacts.restore', $contact->id),
            'method' => 'post',
        ]);
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        Contact::onlyTrashed()->where('family_id', $request->user()->family_id)->findOrFail($id)->restore();

        return back()->with('status', trans('ui.contactos.restored'));
    }

    /** La foto solo se ve con sesión de la familia. */
    public function photo(Request $request, int $id): StreamedResponse
    {
        $contact = $this->find($request, $id);
        abort_unless($contact->photo_path && Storage::disk('local')->exists($contact->photo_path), 404);

        return Storage::disk('local')->response($contact->photo_path, 'contacto.jpg', [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=604800',
        ], 'inline');
    }

    private function find(Request $request, int $id): Contact
    {
        return Contact::where('family_id', $request->user()->family_id)->findOrFail($id);
    }

    /** @return array{fields: array<string, mixed>, photo: ?UploadedFile} */
    private function validated(Request $request): array
    {
        // Acepta "@usuario", "instagram.com/usuario/" o un enlace completo: se guarda solo el usuario.
        $request->merge(['instagram' => self::instagramHandle($request->input('instagram'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'max:60'],
            'phone' => self::PHONE,
            'work_phone' => self::PHONE,
            'email' => ['nullable', 'email:rfc', 'max:120'],
            'instagram' => ['nullable', 'string', 'regex:/^[A-Za-z0-9._]{1,30}$/'],
            'address' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp'],
        ], trans('ui.validation'), trans('ui.contactos.fields'));

        return ['photo' => $request->file('photo'), 'fields' => collect($data)->except('photo')->all()];
    }

    public static function instagramHandle(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('#instagram\.com/([^/?\#\s]+)#i', $text, $m)) {
            $text = $m[1];
        }

        return ltrim(rtrim($text, '/'), '@');
    }
}
