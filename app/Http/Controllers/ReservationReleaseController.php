<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelCaseWithReservationsRequest;
use App\Http\Requests\ReleaseReservationRequest;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Services\Inventory\ReservationReleaseService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReservationReleaseController extends Controller
{
    public function releaseForm(Reservation $reservation, ReservationReleaseService $service): View
    {
        return view('reservations.release', [
            'preview' => $service->reservationPreview($reservation),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function release(
        ReleaseReservationRequest $request,
        Reservation $reservation,
        ReservationReleaseService $service,
    ): RedirectResponse {
        try {
            $operation = $service->release(
                $reservation,
                $request->validated('reason'),
                $request->validated('idempotency_key'),
                $request->user(),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['release' => $exception->getMessage()]);
        }

        $message = $operation->wasRecentlyCreated
            ? 'Reserva liberada. El stock fisico no fue modificado.'
            : 'La solicitud ya se habia procesado; se conserva el resultado original.';

        return redirect()->route('cases.control', $reservation->case_id)->with('status', $message);
    }

    public function cancelForm(SurgeryCase $case, ReservationReleaseService $service): View
    {
        return view('cases.cancel', [
            'case' => $case->load(['institution', 'doctor']),
            'preview' => $service->cancellationPreview($case),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function cancel(
        CancelCaseWithReservationsRequest $request,
        SurgeryCase $case,
        ReservationReleaseService $service,
    ): RedirectResponse {
        try {
            $operation = $service->cancelCase(
                $case,
                $request->validated('reason'),
                $request->validated('idempotency_key'),
                $request->user(),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['cancellation' => $exception->getMessage()]);
        }

        $message = $operation->wasRecentlyCreated
            ? 'Caso cancelado y reservas elegibles liberadas sin modificar el stock fisico.'
            : 'La solicitud ya se habia procesado; se conserva el resultado original.';

        return redirect()->route('cases.control', $case)->with('status', $message);
    }
}
