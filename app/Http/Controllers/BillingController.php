<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBillingRequest;
use App\Models\AuditLog;
use App\Models\BillingRecord;
use App\Models\OperationalAlert;
use App\Models\SurgeryCase;
use App\Services\Operations\BillingService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BillingController extends Controller
{
    private const INVOICE_STATUSES = [
        'pendiente_valorizacion',
        'valorizado',
        'pendiente_oc',
        'pendiente_factura',
        'facturado',
        'no_facturable',
        'costo_cero_pendiente_aprobacion',
        'costo_cero_aprobado',
    ];

    private const PAYMENT_STATUSES = ['pendiente', 'parcial', 'pagado', 'vencido'];

    public function index(BillingService $billingService): View
    {
        $this->authorizeView();
        $billingService->refreshOverdueStatuses();
        $canViewAmounts = auth()->user()?->can('billing.view') ?? false;

        return view('billing.index', [
            'records' => BillingRecord::query()
                ->with(['case.institution', 'case.doctor', 'case.surgeryType'])
                ->latest('id')
                ->paginate(25),
            'canViewAmounts' => $canViewAmounts,
        ]);
    }

    public function show(SurgeryCase $case, BillingService $billingService): View
    {
        $this->authorizeView();
        $billingService->refreshOverdueStatuses();
        $case->load([
            'institution',
            'doctor',
            'surgeryType',
            'billingRecord.documents' => fn ($query) => $query->visibleTo(auth()->user())->with(['uploadedBy', 'validatedBy']),
            'documents' => fn ($query) => $query->visibleTo(auth()->user())->with(['uploadedBy', 'validatedBy']),
            'valuation.lines.product',
            'reconciliation',
        ]);
        $billing = $case->billingRecord ?? new BillingRecord([
            'case_id' => $case->id,
            'amount' => $case->valuation?->total ?? 0,
            'invoice_status' => 'pendiente_valorizacion',
            'payment_status' => 'pendiente',
            'amount_paid' => 0,
        ]);
        $canAudit = auth()->user()?->can('audit.view') ?? false;
        $auditLogs = $canAudit && $billing->exists
            ? AuditLog::query()
                ->with('user')
                ->where('auditable_type', BillingRecord::class)
                ->where('auditable_id', $billing->id)
                ->latest('created_at')
                ->get()
            : collect();

        $slaAlerts = OperationalAlert::query()
            ->whereIn('status', array_merge(OperationalAlert::OPEN_STATUSES, ['expired']))
            ->where(function (Builder $query) use ($case, $billing): void {
                $query->where(function (Builder $caseQuery) use ($case): void {
                    $caseQuery->where('alertable_type', SurgeryCase::class)
                        ->where('alertable_id', $case->id);
                });

                if ($billing->exists) {
                    $query->orWhere(function (Builder $billingQuery) use ($billing): void {
                        $billingQuery->where('alertable_type', BillingRecord::class)
                            ->where('alertable_id', $billing->id);
                    });
                }
            })
            ->latest('detected_at')
            ->get();

        return view('billing.show', [
            'case' => $case,
            'billing' => $billing,
            'canViewAmounts' => auth()->user()?->can('billing.view') ?? false,
            'canUpdate' => auth()->user()?->can('billing.update') ?? false,
            'invoiceStatuses' => self::INVOICE_STATUSES,
            'paymentStatuses' => self::PAYMENT_STATUSES,
            'canAudit' => $canAudit,
            'auditLogs' => $auditLogs,
            'slaAlerts' => $slaAlerts,
        ]);
    }

    public function update(
        UpdateBillingRequest $request,
        SurgeryCase $case,
        BillingService $billingService,
    ): RedirectResponse {
        abort_unless(auth()->user()?->can('billing.update'), 403);

        try {
            $billingService->update($case, $request->validated(), (int) $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['billing' => $exception->getMessage()]);
        }

        return redirect()
            ->route('billing.show', $case)
            ->with('status', 'Estado de facturacion y cobranza actualizado.');
    }

    private function authorizeView(): void
    {
        abort_unless(
            auth()->user()?->can('billing.view') || auth()->user()?->can('commercial.view'),
            403,
        );
    }
}
