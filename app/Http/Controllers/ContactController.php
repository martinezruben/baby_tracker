<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Directorio de la familia: pediatra, emergencias, familiares y cuidadores externos. */
class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Contactos', [
            'contacts' => Contact::where('family_id', $request->user()->family_id)->orderBy('name')
                ->get(['id', 'name', 'role', 'phone', 'email', 'address', 'notes']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Contact::create($this->validated($request) + ['family_id' => $request->user()->family_id]);

        return back()->with('status', trans('ui.contactos.saved'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->find($request, $id)->update($this->validated($request));

        return back()->with('status', trans('ui.contactos.saved'));
    }

    /** Se borra de forma lógica y la Snackbar ofrece deshacer. */
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

    private function find(Request $request, int $id): Contact
    {
        return Contact::where('family_id', $request->user()->family_id)->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-.\s]{5,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:120'],
            'address' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], trans('ui.validation'), trans('ui.contactos.fields'));
    }
}
