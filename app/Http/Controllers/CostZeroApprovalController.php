<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveCostZeroRequest;
use App\Http\Requests\RejectCostZeroRequest;
use App\Models\CaseValuation;
use App\Services\Operations\CostZeroApprovalService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CostZeroApprovalController extends Controller
{
    private const APPROVAL_ROLES = ['Administrador', 'Gerencia', 'Jefe de Linea'];

    public function index(CostZeroApprovalService $service): View
    {
        $this->authorizeApproval();
        $approvals = $service->pending();
        $approvals->load([
            'documents' => fn ($query) => $query->visibleTo(auth()->user())->with(['uploadedBy', 'validatedBy']),
        ]);

        return view('approvals.cost-zero', [
            'approvals' => $approvals,
        ]);
    }

    public function approve(
        ApproveCostZeroRequest $request,
        CaseValuation $valuation,
        CostZeroApprovalService $service,
    ): RedirectResponse {
        $this->authorizeApproval();

        try {
            $service->approve($valuation, (int) $request->user()->id, $request->validated('evidence'));
        } catch (DomainException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        return redirect()
            ->route('approvals.cost-zero.index')
            ->with('status', 'Costo cero aprobado correctamente.');
    }

    public function reject(
        RejectCostZeroRequest $request,
        CaseValuation $valuation,
        CostZeroApprovalService $service,
    ): RedirectResponse {
        $this->authorizeApproval();

        try {
            $service->reject($valuation, (int) $request->user()->id, $request->validated('reason'));
        } catch (DomainException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        return redirect()
            ->route('approvals.cost-zero.index')
            ->with('status', 'Costo cero rechazado y enviado a correccion.');
    }

    private function authorizeApproval(): void
    {
        $user = auth()->user();

        abort_unless(
            $user?->can('approvals.approve') && $user->hasAnyRole(self::APPROVAL_ROLES),
            403,
        );
    }
}
