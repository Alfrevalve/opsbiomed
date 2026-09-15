<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteReconciliationRequest;
use App\Models\SurgeryCase;
use App\Services\Operations\SurgeryCaseReconciliationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function show(SurgeryCase $case, SurgeryCaseReconciliationService $service): View
    {
        $this->authorizeReconciliation();
        $case->load(['institution', 'doctor', 'patient', 'surgeryType']);
        $summary = $service->summary($case);

        return view('cases.reconciliation', compact('case', 'summary'));
    }

    public function store(
        CompleteReconciliationRequest $request,
        SurgeryCase $case,
        SurgeryCaseReconciliationService $service,
    ): RedirectResponse {
        $this->authorizeReconciliation();

        try {
            $service->reconcile($case, $request->validated(), (int) $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['reconciliation' => $exception->getMessage()]);
        }

        return redirect()
            ->route('cases.show', $case)
            ->with('status', 'Conciliacion registrada y auditada correctamente.');
    }

    private function authorizeReconciliation(): void
    {
        $user = auth()->user();

        abort_unless(
            $user?->can('cases.close')
                || $user?->can('billing.view')
                || $user?->can('billing.update'),
            403,
        );
    }
}
