<?php

namespace App\Services\Inventory;

use App\Enums\CaseStatus;
use App\Models\InventoryLot;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Services\Audit\AuditLogger;
use App\Services\Traceability\TraceCodeService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReservationService
{
    public function __construct(
        private readonly StockEligibilityService $stockEligibility,
        private readonly AuditLogger $auditLogger,
        private readonly TraceCodeService $traceCodeService,
    ) {}

    public function reserveRequiredKit(SurgeryCase $case): void
    {
        DB::transaction(function () use ($case): void {
            $rules = $case->surgeryType->kitRules()->where('required', true)->get();

            foreach ($rules as $rule) {
                $eligible = $this->stockEligibility->eligibleQuantityForRule($rule);

                if ($eligible < $rule->min_qty) {
                    throw new RuntimeException("Stock insuficiente para regla {$rule->id}. Disponible: {$eligible}; minimo: {$rule->min_qty}.");
                }

                $pending = $rule->min_qty;

                $lots = $this->stockEligibility->queryForRule($rule)
                    ->orderByRaw('expiry is null')
                    ->orderBy('expiry')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if ($pending <= 0) {
                        break;
                    }

                    $reservedQty = min($pending, $this->stockEligibility->netAvailableForLot($lot));

                    if ($reservedQty <= 0) {
                        continue;
                    }

                    $reservation = Reservation::create([
                        'case_id' => $case->id,
                        'inventory_lot_id' => $lot->id,
                        'quantity' => $reservedQty,
                        'status' => 'active',
                        'reserved_by' => auth()->id(),
                        'expires_at' => $case->scheduled_at?->copy()->addDay(),
                    ]);

                    $this->auditLogger->record('reservation.created', $reservation, [], [
                        'case_id' => $case->id,
                        'inventory_lot_id' => $lot->id,
                        'quantity' => $reservedQty,
                        'status' => 'active',
                    ]);
                    $this->traceCodeService->record('reserved', $reservation, $case->id, [
                        'inventory_lot_id' => $lot->id,
                        'quantity' => $reservedQty,
                    ]);

                    $pending -= $reservedQty;
                }
            }

            $case->update(['status' => CaseStatus::Reservado]);
            $this->auditLogger->record('case.reserved', $case, [], ['status' => CaseStatus::Reservado->value]);
        });
    }

    public function reserveLot(SurgeryCase $case, int $lotId, int $quantity): Reservation
    {
        if ($quantity <= 0) {
            throw new RuntimeException('La cantidad a reservar debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($case, $lotId, $quantity): Reservation {
            $lot = InventoryLot::query()
                ->with(['product', 'warehouse'])
                ->lockForUpdate()
                ->findOrFail($lotId);

            if (! $this->stockEligibility->isEligible($lot)) {
                throw new RuntimeException('El lote seleccionado no esta disponible para reserva.');
            }

            $availableNet = $this->stockEligibility->netAvailableForLot($lot);

            if ($quantity > $availableNet) {
                throw new RuntimeException("La cantidad supera el disponible neto del lote ({$availableNet}).");
            }

            $reservation = Reservation::create([
                'case_id' => $case->id,
                'inventory_lot_id' => $lot->id,
                'quantity' => $quantity,
                'status' => 'active',
                'reserved_by' => auth()->id(),
                'expires_at' => $case->scheduled_at?->copy()->addDay(),
            ]);

            $this->auditLogger->record('reservation.created', $reservation, [], [
                'case_id' => $case->id,
                'inventory_lot_id' => $lot->id,
                'quantity' => $quantity,
                'status' => 'active',
            ]);
            $this->traceCodeService->record('reserved', $reservation, $case->id, [
                'inventory_lot_id' => $lot->id,
                'quantity' => $quantity,
            ]);

            if (! in_array($case->status, [CaseStatus::Reservado, CaseStatus::Reservada], true)) {
                $case->update(['status' => CaseStatus::Reservado]);
            }

            return $reservation;
        });
    }
}
