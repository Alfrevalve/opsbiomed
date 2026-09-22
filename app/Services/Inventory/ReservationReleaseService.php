<?php

namespace App\Services\Inventory;

use App\Enums\CaseStatus;
use App\Models\CaseMaterialSent;
use App\Models\CaseMaterialUsed;
use App\Models\CaseReturn;
use App\Models\Failure;
use App\Models\InventoryLot;
use App\Models\Reservation;
use App\Models\ReservationReleaseOperation;
use App\Models\SurgeryCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Traceability\TraceCodeService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReservationReleaseService
{
    private const OPERATION_RELEASE = 'reservation_release';

    private const OPERATION_CANCEL = 'case_cancel';

    private const CLOSED_CASE_STATUSES = [
        CaseStatus::PendienteCierre,
        CaseStatus::Cerrado,
        CaseStatus::Cerrada,
        CaseStatus::Conciliacion,
        CaseStatus::Facturacion,
        CaseStatus::Facturada,
        CaseStatus::Cancelado,
    ];

    private const PHYSICALLY_TRANSFERRED_STATUSES = [
        CaseStatus::Internado,
        CaseStatus::EnSala,
        CaseStatus::EnCirugia,
        CaseStatus::PendienteCierre,
        CaseStatus::Cerrado,
        CaseStatus::Cerrada,
        CaseStatus::Conciliacion,
        CaseStatus::Facturacion,
        CaseStatus::Facturada,
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TraceCodeService $traceCodeService,
        private readonly StockEligibilityService $stockEligibility,
    ) {}

    /** @return array{reservation: Reservation, eligible: bool, reason: ?string} */
    public function reservationPreview(Reservation $reservation): array
    {
        $reservation->loadMissing([
            'case.preparation',
            'inventoryLot.product',
            'inventoryLot.warehouse',
        ]);

        $reason = $this->releaseBlockReason($reservation, $reservation->case, $reservation->inventoryLot);

        return [
            'reservation' => $reservation,
            'eligible' => $reason === null,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{can_cancel: bool, case_reason: ?string, reservations: Collection<int, array{reservation: Reservation, eligible: bool, reason: ?string}>}
     */
    public function cancellationPreview(SurgeryCase $surgeryCase): array
    {
        $case = $surgeryCase->loadMissing(['preparation']);
        $reservations = $case->reservations()
            ->where('status', 'active')
            ->with(['inventoryLot.product', 'inventoryLot.warehouse'])
            ->orderBy('id')
            ->get()
            ->map(function (Reservation $reservation) use ($case): array {
                $reason = $this->releaseBlockReason($reservation, $case, $reservation->inventoryLot);

                return [
                    'reservation' => $reservation,
                    'eligible' => $reason === null,
                    'reason' => $reason,
                ];
            });
        $caseReason = $this->caseCancellationBlockReason($case);

        return [
            'can_cancel' => $caseReason === null && $reservations->every(
                fn (array $row): bool => $row['eligible'],
            ),
            'case_reason' => $caseReason,
            'reservations' => $reservations,
        ];
    }

    public function release(
        Reservation $reservation,
        string $reason,
        string $idempotencyKey,
        User $user,
    ): ReservationReleaseOperation {
        return DB::transaction(function () use ($reservation, $reason, $idempotencyKey, $user): ReservationReleaseOperation {
            $case = SurgeryCase::query()->lockForUpdate()->findOrFail($reservation->case_id);
            $lockedReservation = Reservation::query()
                ->where('case_id', $case->id)
                ->lockForUpdate()
                ->findOrFail($reservation->id);
            $existing = $this->existingOperation($idempotencyKey, self::OPERATION_RELEASE, $case, $lockedReservation, $reason, $user);

            if ($existing !== null) {
                return $existing;
            }

            $lot = InventoryLot::query()
                ->with(['product', 'warehouse'])
                ->lockForUpdate()
                ->find($lockedReservation->inventory_lot_id);
            $lockedReservation->setRelation('case', $case);
            $lockedReservation->setRelation('inventoryLot', $lot);
            $blockReason = $this->releaseBlockReason($lockedReservation, $case, $lot);

            if ($blockReason !== null) {
                throw new DomainException($blockReason);
            }

            $operation = $this->startOperation(
                self::OPERATION_RELEASE,
                $case,
                $lockedReservation,
                $reason,
                $idempotencyKey,
                $user,
            );

            $this->releaseLockedReservation($lockedReservation, $case, $lot, $reason, $idempotencyKey);
            $availableNet = $this->stockEligibility->netAvailableForLot($lot->refresh());
            $operation->update([
                'status' => 'completed',
                'result' => [
                    'released_reservation_ids' => [$lockedReservation->id],
                    'available_net_by_lot' => [$lot->id => $availableNet],
                ],
            ]);

            return $operation->refresh();
        });
    }

    public function cancelCase(
        SurgeryCase $surgeryCase,
        string $reason,
        string $idempotencyKey,
        User $user,
    ): ReservationReleaseOperation {
        return DB::transaction(function () use ($surgeryCase, $reason, $idempotencyKey, $user): ReservationReleaseOperation {
            $case = SurgeryCase::query()->lockForUpdate()->findOrFail($surgeryCase->id);
            $existing = $this->existingOperation($idempotencyKey, self::OPERATION_CANCEL, $case, null, $reason, $user);

            if ($existing !== null) {
                return $existing;
            }

            $caseReason = $this->caseCancellationBlockReason($case);

            if ($caseReason !== null) {
                throw new DomainException($caseReason);
            }

            $reservations = Reservation::query()
                ->where('case_id', $case->id)
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $lotIds = $reservations->pluck('inventory_lot_id')->unique()->sort()->values();
            $lots = InventoryLot::query()
                ->with(['product', 'warehouse'])
                ->whereIn('id', $lotIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $blockers = [];

            foreach ($reservations as $lockedReservation) {
                $lot = $lots->get($lockedReservation->inventory_lot_id);
                $lockedReservation->setRelation('case', $case);
                $lockedReservation->setRelation('inventoryLot', $lot);
                $blockReason = $this->releaseBlockReason($lockedReservation, $case, $lot);

                if ($blockReason !== null) {
                    $blockers[] = 'Reserva #'.$lockedReservation->id.': '.$blockReason;
                }
            }

            if ($blockers !== []) {
                throw new DomainException('No se cancelo el caso. Ninguna reserva fue modificada. '.implode(' ', $blockers));
            }

            $operation = $this->startOperation(
                self::OPERATION_CANCEL,
                $case,
                null,
                $reason,
                $idempotencyKey,
                $user,
            );
            $availableNetByLot = [];

            foreach ($reservations as $lockedReservation) {
                $lot = $lots->get($lockedReservation->inventory_lot_id);
                $this->releaseLockedReservation($lockedReservation, $case, $lot, $reason, $idempotencyKey);
                $availableNetByLot[$lot->id] = $this->stockEligibility->netAvailableForLot($lot->refresh());
            }

            $beforeStatus = $case->status->value;
            $case->update(['status' => CaseStatus::Cancelado]);
            $this->auditLogger->record('case.cancelled_with_reservations', $case, [
                'status' => $beforeStatus,
            ], [
                'case_id' => $case->id,
                'status' => CaseStatus::Cancelado->value,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'released_reservation_ids' => $reservations->modelKeys(),
            ]);
            $operation->update([
                'status' => 'completed',
                'result' => [
                    'released_reservation_ids' => $reservations->modelKeys(),
                    'available_net_by_lot' => $availableNetByLot,
                    'case_status' => CaseStatus::Cancelado->value,
                ],
            ]);

            return $operation->refresh();
        });
    }

    private function releaseLockedReservation(
        Reservation $reservation,
        SurgeryCase $case,
        InventoryLot $lot,
        string $reason,
        string $idempotencyKey,
    ): void {
        $product = $lot->product;
        $warehouse = $lot->warehouse;
        $quantity = (int) $reservation->quantity;
        $before = [
            'case_id' => $case->id,
            'reservation_id' => $reservation->id,
            'product_id' => $lot->product_id,
            'product_code' => $product?->product_code,
            'product' => $product?->name,
            'inventory_lot_id' => $lot->id,
            'lot' => $lot->lot,
            'serial' => $lot->serial,
            'warehouse_id' => $lot->warehouse_id,
            'warehouse' => $warehouse?->name,
            'quantity' => $quantity,
            'status' => $reservation->status,
        ];

        $reservation->update(['status' => 'released']);
        $reservedAfter = (int) DB::table('reservations')
            ->where('inventory_lot_id', $lot->id)
            ->where('status', 'active')
            ->sum('quantity');

        if ($reservedAfter > max(0, (int) $lot->quantity)) {
            throw new DomainException('La reserva no se libero porque el stock fisico quedaria por debajo de otras reservas activas.');
        }

        $this->auditLogger->record('reservation.released', $reservation, $before, [
            ...$before,
            'status' => 'released',
            'reserved_quantity_after' => $reservedAfter,
            'available_net_after' => $this->stockEligibility->netAvailableForLot($lot),
            'reason' => $reason,
            'idempotency_key' => $idempotencyKey,
        ]);
        $this->traceCodeService->record('released', $reservation, $case->id, [
            'quantity' => $quantity,
            'status' => 'released',
        ]);
    }

    private function releaseBlockReason(
        Reservation $reservation,
        ?SurgeryCase $case,
        ?InventoryLot $lot,
    ): ?string {
        if ($reservation->status !== 'active') {
            return 'La reserva ya no esta activa.';
        }

        if ($case === null || $lot === null) {
            return 'El caso o el lote asociado ya no existe; solicite revision administrativa.';
        }

        if ($this->caseCancellationBlockReason($case) !== null) {
            return 'El caso se encuentra en una etapa que bloquea la liberacion directa.';
        }

        if (in_array($case->status, self::PHYSICALLY_TRANSFERRED_STATUSES, true)
            || CaseMaterialSent::query()->where('reservation_id', $reservation->id)->exists()
            || $case->preparation?->dispatched_at !== null) {
            return 'El material ya fue entregado, internado o transferido. Use la devolucion y la inspeccion postoperatoria.';
        }

        if (CaseMaterialUsed::query()->where('case_id', $case->id)->exists()) {
            return 'El caso ya tiene cierre o consumo quirurgico asociado; no se permite liberar directamente.';
        }

        if ($case->reconciliation()->exists()) {
            return 'El caso ya tiene una conciliacion asociada; requiere revision administrativa.';
        }

        if (CaseReturn::query()->where('case_id', $case->id)->exists()) {
            return 'El caso tiene una devolucion asociada. Complete la inspeccion antes de continuar.';
        }

        if (Failure::query()->where('case_id', $case->id)->exists()
            || Failure::query()->where('inventory_lot_id', $lot->id)->exists()) {
            return 'Existe una falla tecnica asociada al caso o al lote. Complete el flujo tecnico antes de liberar.';
        }

        return null;
    }

    private function caseCancellationBlockReason(SurgeryCase $case): ?string
    {
        if (in_array($case->status, self::CLOSED_CASE_STATUSES, true)) {
            return 'El caso esta cerrado, en cierre, conciliacion, facturacion o ya cancelado.';
        }

        return null;
    }

    private function existingOperation(
        string $idempotencyKey,
        string $type,
        SurgeryCase $case,
        ?Reservation $reservation,
        string $reason,
        User $user,
    ): ?ReservationReleaseOperation {
        $operation = ReservationReleaseOperation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        if ($operation === null) {
            return null;
        }

        if ($operation->operation_type !== $type
            || (int) $operation->case_id !== (int) $case->id
            || (int) $operation->reservation_id !== (int) ($reservation?->id ?? 0)
            || (int) $operation->user_id !== (int) $user->id
            || $operation->reason !== $reason
            || $operation->status !== 'completed') {
            throw new DomainException('La clave idempotente ya fue utilizada con otra operacion o con datos distintos.');
        }

        return $operation;
    }

    private function startOperation(
        string $type,
        SurgeryCase $case,
        ?Reservation $reservation,
        string $reason,
        string $idempotencyKey,
        User $user,
    ): ReservationReleaseOperation {
        return ReservationReleaseOperation::create([
            'idempotency_key' => $idempotencyKey,
            'operation_type' => $type,
            'case_id' => $case->id,
            'reservation_id' => $reservation?->id,
            'user_id' => $user->id,
            'reason' => $reason,
            'status' => 'processing',
        ]);
    }
}
