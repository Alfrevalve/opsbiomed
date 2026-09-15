<?php

namespace App\Services\Operations;

use App\Models\Approval;
use App\Models\BillingRecord;
use App\Models\CaseValuation;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CostZeroApprovalService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return Collection<int, Approval>
     */
    public function pending(): Collection
    {
        return Approval::query()
            ->where('type', 'cost_zero')
            ->where('status', 'pendiente_aprobacion')
            ->with(['case.institution', 'case.doctor', 'valuation.lines.product', 'requestedBy'])
            ->latest('id')
            ->get();
    }

    public function approve(CaseValuation $valuation, int $userId, ?string $evidence = null): Approval
    {
        return $this->resolve($valuation, $userId, 'aprobado', $evidence, null);
    }

    public function reject(CaseValuation $valuation, int $userId, string $reason): Approval
    {
        if (blank($reason)) {
            throw new DomainException('El rechazo de costo cero requiere un motivo.');
        }

        return $this->resolve($valuation, $userId, 'rechazado', null, $reason);
    }

    private function resolve(
        CaseValuation $valuation,
        int $userId,
        string $status,
        ?string $evidence,
        ?string $reason,
    ): Approval {
        return DB::transaction(function () use ($valuation, $userId, $status, $evidence, $reason): Approval {
            $lockedValuation = CaseValuation::query()->lockForUpdate()->findOrFail($valuation->id);
            $approval = Approval::query()
                ->where('valuation_id', $lockedValuation->id)
                ->where('type', 'cost_zero')
                ->where('status', 'pendiente_aprobacion')
                ->lockForUpdate()
                ->first();

            if (! $approval) {
                throw new DomainException('La valorizacion no tiene una aprobacion de costo cero pendiente.');
            }

            $approval->update([
                'status' => $status,
                'approved_by' => $userId,
                'approved_at' => now(),
                'evidence' => $evidence,
                'reason' => $reason,
            ]);
            $lockedValuation->update([
                'status' => $status === 'aprobado'
                    ? 'costo_cero_aprobado'
                    : 'pendiente_valorizacion',
            ]);

            $billingStatus = $status === 'aprobado'
                ? 'costo_cero_aprobado'
                : 'pendiente_valorizacion';
            $billing = BillingRecord::query()->where('case_id', $approval->case_id)->first();
            $billingBefore = $billing?->only(['invoice_status', 'amount', 'amount_paid']);
            $billing ??= BillingRecord::create([
                'case_id' => $approval->case_id,
                'amount' => (float) $lockedValuation->total,
                'amount_paid' => 0,
                'invoice_status' => 'pendiente_valorizacion',
                'payment_status' => 'pendiente',
            ]);
            $billing->update([
                'invoice_status' => $billingStatus,
                'amount' => $status === 'aprobado' ? 0 : (float) $billing->amount,
                'amount_paid' => $status === 'aprobado' ? 0 : $billing->amount_paid,
                'payment_status' => $status === 'aprobado' ? 'pagado' : 'pendiente',
                'no_billing_reason' => $status === 'aprobado' ? 'Costo cero aprobado.' : null,
            ]);

            $action = $status === 'aprobado' ? 'cost_zero.approved' : 'cost_zero.rejected';
            $this->auditLogger->record($action, $approval, [], [
                'valuation_id' => $lockedValuation->id,
                'case_id' => $approval->case_id,
                'status' => $status,
                'reason' => $reason,
            ]);
            $this->auditLogger->record('billing.status.updated', $billing, $billingBefore ?? [], [
                'invoice_status' => $billing->invoice_status,
                'amount' => $billing->amount,
                'amount_paid' => $billing->amount_paid,
            ]);

            return $approval->refresh();
        });
    }
}
