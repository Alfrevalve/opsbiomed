<?php

namespace App\Services\Operations;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\Approval;
use App\Models\BillingRecord;
use App\Models\CaseMaterialUsed;
use App\Models\CaseReturn;
use App\Models\CaseValuation;
use App\Models\Failure;
use App\Models\InventoryLot;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Traceability\TraceCodeService;
use DomainException;
use Illuminate\Support\Facades\DB;

class SurgeryCaseClosingService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ProductPriceService $priceService,
        private readonly TraceCodeService $traceCodeService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function close(SurgeryCase $surgeryCase, array $data, int $userId): CaseValuation
    {
        return DB::transaction(function () use ($surgeryCase, $data, $userId): CaseValuation {
            $case = SurgeryCase::query()
                ->lockForUpdate()
                ->findOrFail($surgeryCase->id);
            $user = User::query()->findOrFail($userId);

            if (in_array($case->status, [CaseStatus::Cerrado, CaseStatus::Cerrada, CaseStatus::Facturada], true)) {
                throw new DomainException('La cirugia ya esta cerrada y no permite un nuevo cierre.');
            }

            $reservations = Reservation::query()
                ->where('case_id', $case->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                throw new DomainException('No hay reservas activas que conciliar para este caso.');
            }

            $submittedMaterials = collect($data['materials'] ?? [])->keyBy(
                fn (array $material): int => (int) $material['reservation_id'],
            );
            $reservationIds = $reservations->modelKeys();

            if ($submittedMaterials->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all() !== collect($reservationIds)->sort()->values()->all()) {
                throw new DomainException('Debe indicar el destino de cada material reservado, sin agregar reservas ajenas.');
            }

            $reservations->load(['inventoryLot.product', 'inventoryLot.warehouse']);
            $evidenceDescription = trim((string) ($data['evidence_description'] ?? ''));
            $evidenceReference = filled($data['evidence_reference'] ?? null)
                ? trim((string) $data['evidence_reference'])
                : null;
            $observations = $data['observations'] ?? null;
            $valuationLines = collect();
            $hasCostZeroApproval = false;
            $subtotal = 0.0;

            foreach ($reservations as $reservation) {
                $line = $submittedMaterials->get($reservation->id);
                $lot = InventoryLot::query()
                    ->with('product')
                    ->lockForUpdate()
                    ->findOrFail($reservation->inventory_lot_id);

                if (filled($line['inventory_lot_trace_code'] ?? null)) {
                    $this->traceCodeService->record('scanned', $lot, $case->id, [
                        'source' => 'case_close',
                        'reservation_id' => $reservation->id,
                    ]);
                }

                $reservedQty = (int) $reservation->quantity;
                $usedQty = (int) $line['used_qty'];
                $unusedOpenedQty = (int) $line['unused_opened_qty'];
                $returnedQty = (int) $line['returned_qty'];
                $failureQty = (int) $line['failure_qty'];
                $accountedQty = $usedQty + $unusedOpenedQty + $returnedQty + $failureQty;

                if ($accountedQty > $reservedQty) {
                    throw new DomainException("Las cantidades del lote {$lot->lot} superan la reserva de {$reservedQty} unidades.");
                }

                $differenceQty = $reservedQty - $accountedQty;
                $differenceReason = filled($line['difference_reason'] ?? null)
                    ? trim((string) $line['difference_reason'])
                    : null;

                if ($differenceQty > 0 && blank($differenceReason)) {
                    throw new DomainException("La diferencia del lote {$lot->lot} requiere un motivo de conciliacion.");
                }

                if ($usedQty + $unusedOpenedQty + $failureQty > (int) $lot->quantity) {
                    throw new DomainException("La cantidad a descontar del lote {$lot->lot} supera su stock fisico.");
                }

                $costZero = $this->isTrue($line['cost_zero'] ?? false);
                $priceResolution = $this->priceService->resolveLine(
                    $line,
                    $lot->product_id,
                    $case->institution_id,
                    $case->doctor_id,
                    $usedQty,
                    $costZero,
                    $user,
                );
                $unitPrice = $priceResolution['unit_price'];
                $lineSubtotal = round($usedQty * $unitPrice, 2);
                $requiresApproval = $costZero && $usedQty > 0;
                $hasCostZeroApproval = $hasCostZeroApproval || $requiresApproval;
                $subtotal += $lineSubtotal;

                $materialUsed = CaseMaterialUsed::create([
                    'case_id' => $case->id,
                    'reservation_id' => $reservation->id,
                    'inventory_lot_id' => $lot->id,
                    'reserved_qty' => $reservedQty,
                    'opened_qty' => $usedQty + $unusedOpenedQty + $failureQty,
                    'used_qty' => $usedQty,
                    'unused_opened_qty' => $unusedOpenedQty,
                    'returned_qty' => $returnedQty,
                    'failure_qty' => $failureQty,
                    'difference_qty' => $differenceQty,
                    'difference_reason' => $differenceReason,
                    'evidence_description' => $evidenceDescription,
                    'evidence_reference' => $evidenceReference,
                    'failure_description' => $line['failure_description'] ?? null,
                    'unit_price' => $unitPrice,
                    'minimum_unit_price' => $priceResolution['minimum_price'],
                    'price_below_minimum' => $priceResolution['price_below_minimum'],
                    'subtotal' => $lineSubtotal,
                    'cost_zero' => $costZero,
                    'cost_zero_reason' => $line['cost_zero_reason'] ?? null,
                    'requires_approval' => $requiresApproval,
                    'notes' => $observations,
                    'reported_by' => $userId,
                ]);
                $this->auditLogger->record('consumption.recorded', $materialUsed, [], [
                    'case_id' => $case->id,
                    'reservation_id' => $reservation->id,
                    'reserved_qty' => $reservedQty,
                    'used_qty' => $usedQty,
                    'returned_qty' => $returnedQty,
                    'failure_qty' => $failureQty,
                    'difference_qty' => $differenceQty,
                    'price_below_minimum' => $priceResolution['price_below_minimum'],
                ]);
                if ($usedQty > 0) {
                    $this->traceCodeService->record('used', $reservation, $case->id, [
                        'inventory_lot_id' => $lot->id,
                        'quantity' => $usedQty,
                    ]);
                }

                if ($returnedQty > 0) {
                    $caseReturn = CaseReturn::create([
                        'case_id' => $case->id,
                        'inventory_lot_id' => $lot->id,
                        'returned_qty' => $returnedQty,
                        'condition' => 'pendiente_inspeccion',
                    ]);
                    $this->auditLogger->record('return.pending_inspection', $caseReturn, [], [
                        'case_id' => $case->id,
                        'inventory_lot_id' => $lot->id,
                        'returned_qty' => $returnedQty,
                        'condition' => 'pendiente_inspeccion',
                    ]);
                    $this->traceCodeService->record('returned', $caseReturn, $case->id, [
                        'inventory_lot_id' => $lot->id,
                        'quantity' => $returnedQty,
                    ]);
                }

                if ($failureQty > 0) {
                    $failure = Failure::create([
                        'case_id' => $case->id,
                        'inventory_lot_id' => $lot->id,
                        'product_id' => $lot->product_id,
                        'failure_type' => 'otro',
                        'occurrence_moment' => 'durante_cirugia',
                        'severity' => 'alta',
                        'status' => 'bloqueada',
                        'preventive_block' => true,
                        'description' => $line['failure_description'],
                        'reported_by' => $userId,
                    ]);
                    $failureSnapshot = $failure->only(['id', 'case_id', 'inventory_lot_id', 'product_id', 'severity', 'status', 'preventive_block', 'description']);
                    $this->auditLogger->record('failure.detected', $failure, [], [
                        'case_id' => $case->id,
                        'inventory_lot_id' => $lot->id,
                        'failure_qty' => $failureQty,
                    ]);
                    $this->auditLogger->record('failure.reported', $failure, [], $failureSnapshot);
                    $this->traceCodeService->record('failed', $failure, $case->id, [
                        'inventory_lot_id' => $lot->id,
                        'quantity' => $failureQty,
                    ]);
                }

                $lotBefore = $lot->only(['id', 'quantity', 'status', 'eligible_flag', 'block_reason']);
                $quantityAfter = (int) $lot->quantity - $usedQty - $unusedOpenedQty - $failureQty;
                $lotStatus = match (true) {
                    $failureQty > 0 => InventoryStatus::FallaPreventiva,
                    $returnedQty > 0 || $unusedOpenedQty > 0 || $differenceQty > 0 => InventoryStatus::Cuarentena,
                    default => $lot->status,
                };
                $lot->update([
                    'quantity' => $quantityAfter,
                    'status' => $lotStatus,
                    'eligible_flag' => $lotStatus === InventoryStatus::Apto && $quantityAfter > 0 && $lot->eligible_flag,
                    'block_reason' => $lotStatus !== InventoryStatus::Apto
                        ? 'Material pendiente de inspeccion, conciliacion o investigacion de falla.'
                        : $lot->block_reason,
                ]);
                if ($failureQty > 0) {
                    $this->auditLogger->record('inventory.blocked', $lot, $lotBefore, $lot->only(['id', 'quantity', 'status', 'eligible_flag', 'block_reason']) + [
                        'failure_id' => $failure->id,
                    ]);
                    $this->traceCodeService->record('blocked', $lot, $case->id, [
                        'failure_id' => $failure->id,
                        'reason' => 'Falla detectada durante cierre quirurgico.',
                    ]);
                }

                $reservationStatus = match (true) {
                    $usedQty >= $reservedQty => 'consumed',
                    $usedQty > 0 => 'partially_consumed',
                    $returnedQty > 0 || $unusedOpenedQty > 0 => 'returned_pending_inspection',
                    $failureQty > 0 => 'incident_reported',
                    default => 'reconciled',
                };
                $reservation->update(['status' => $reservationStatus]);

                $valuationLines->push([
                    'reservation_id' => $reservation->id,
                    'product_id' => $lot->product_id,
                    'inventory_lot_id' => $lot->id,
                    'quantity_used' => $usedQty,
                    'unit_price' => $unitPrice,
                    'minimum_unit_price' => $priceResolution['minimum_price'],
                    'price_below_minimum' => $priceResolution['price_below_minimum'],
                    'subtotal' => $lineSubtotal,
                    'cost_zero' => $costZero,
                    'cost_zero_reason' => $line['cost_zero_reason'] ?? null,
                    'requires_approval' => $requiresApproval,
                ]);
            }

            $valuation = CaseValuation::create([
                'case_id' => $case->id,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'currency' => 'PEN',
                'status' => $hasCostZeroApproval
                    ? 'costo_cero_pendiente_aprobacion'
                    : 'preliminar',
                'created_by' => $userId,
            ]);
            foreach ($valuationLines as $line) {
                $valuationLine = $valuation->lines()->create($line + ['case_id' => $case->id]);
                $this->auditLogger->record('valuation.line.created', $valuationLine, [], $line);
            }
            $this->auditLogger->record('valuation.created', $valuation, [], [
                'case_id' => $case->id,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'lines' => $valuationLines->count(),
            ]);

            if ($hasCostZeroApproval) {
                $approval = Approval::create([
                    'case_id' => $case->id,
                    'valuation_id' => $valuation->id,
                    'type' => 'cost_zero',
                    'status' => 'pendiente_aprobacion',
                    'requested_by' => $userId,
                    'evidence' => $data['evidence_description'],
                ]);
                $this->auditLogger->record('cost_zero.requested', $approval, [], [
                    'case_id' => $case->id,
                    'valuation_id' => $valuation->id,
                    'status' => 'pendiente_aprobacion',
                ]);
            }

            $billingStatus = $hasCostZeroApproval
                ? 'costo_cero_pendiente_aprobacion'
                : ($subtotal > 0 ? 'valorizado' : 'no_facturable');
            $billingBefore = BillingRecord::query()->where('case_id', $case->id)->first();
            $billingRecord = BillingRecord::updateOrCreate(
                ['case_id' => $case->id],
                [
                    'amount' => $subtotal,
                    'currency' => 'PEN',
                    'invoice_status' => $billingStatus,
                    'payment_status' => 'pendiente',
                    'no_billing_reason' => $billingStatus === 'no_facturable'
                        ? 'No se registraron materiales usados valorizables.'
                        : null,
                ],
            );
            $this->auditLogger->record('billing.status.updated', $billingRecord, $billingBefore?->only(['invoice_status', 'amount']) ?? [], [
                'invoice_status' => $billingRecord->invoice_status,
                'amount' => $billingRecord->amount,
            ]);

            $beforeStatus = $case->status?->value;
            $case->update(['status' => CaseStatus::Cerrado]);
            $this->auditLogger->record('case.closed', $case, ['status' => $beforeStatus], [
                'status' => CaseStatus::Cerrado->value,
                'billing_status' => $billingStatus,
                'valuation_total' => $subtotal,
            ]);

            return $valuation->refresh();
        });
    }

    private function isTrue(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'on'], true);
    }
}
