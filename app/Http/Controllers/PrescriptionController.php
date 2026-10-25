<?php

namespace App\Http\Controllers;

use App\Actions\RecordDose;
use App\Actions\SavePrescription;
use App\Http\Requests\PrescriptionRequest;
use App\Models\Appointment;
use App\Models\Prescription;
use App\Models\PrescriptionDose;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    public function store(PrescriptionRequest $request, Appointment $appointment, SavePrescription $save): RedirectResponse
    {
        abort_unless($appointment->family_id === $request->user()->family_id, 404);

        $save->handle($request->user(), $appointment->baby, $appointment, $request->prescriptionData());

        return back()->with('status', trans('ui.citas.prescription_saved'));
    }

    public function destroy(Request $request, Prescription $prescription, SavePrescription $save): RedirectResponse
    {
        abort_unless($prescription->family_id === $request->user()->family_id, 404);

        $save->cancel($prescription);

        return back()->with('status', trans('ui.citas.prescription_cancelled'));
    }

    public function give(Request $request, PrescriptionDose $dose, RecordDose $doses): RedirectResponse
    {
        $this->authorizeDose($request, $dose);
        $doses->give($dose, $request->user());

        return back()->with('undo', $this->undo('ui.citas.dose_given', $dose));
    }

    public function skip(Request $request, PrescriptionDose $dose, RecordDose $doses): RedirectResponse
    {
        $this->authorizeDose($request, $dose);
        $doses->skip($dose, $request->user());

        return back()->with('undo', $this->undo('ui.citas.dose_skipped', $dose));
    }

    public function revert(Request $request, PrescriptionDose $dose, RecordDose $doses): RedirectResponse
    {
        $this->authorizeDose($request, $dose);
        $doses->revert($dose);

        return back()->with('status', trans('ui.registrar.undone'));
    }

    private function authorizeDose(Request $request, PrescriptionDose $dose): void
    {
        abort_unless($dose->prescription->family_id === $request->user()->family_id, 404);
    }

    private function undo(string $message, PrescriptionDose $dose): array
    {
        return ['message' => trans($message), 'url' => route('doses.revert', $dose), 'method' => 'post'];
    }
}
