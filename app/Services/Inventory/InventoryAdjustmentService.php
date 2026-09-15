<?php

namespace App\Services\Inventory;

use App\Enums\InventoryStatus;
use App\Models\InventoryAdjustment;
use App\Models\InventoryLot;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(InventoryLot $inventoryLot, array $data, int $userId): InventoryAdjustment
    {
        return DB::transaction(function () use ($inventoryLot, $data, $userId): InventoryAdjustment {
            $lot = InventoryLot::query()->lockForUpdate()->findOrFail($inventoryLot->id);
            $adjustment = (int) $data['quantity_adjustment'];
            $newQuantity = (int) $lot->quantity + $adjustment;
            $allowInconsistency = (bool) ($data['allow_inconsistency'] ?? false);

            if ($newQuantity < 0 && (! $allowInconsistency || ! auth()->user()?->can('inventory.audit'))) {
                throw new DomainException('El ajuste no puede dejar stock negativo sin registrar una inconsistencia autorizada.');
            }

            $evidencePath = null;
            if (($data['evidence'] ?? null) instanceof UploadedFile) {
                $evidencePath = $data['evidence']->store('inventory-adjustments', 'private');
            }

            $before = [
                'quantity' => $lot->quantity,
                'status' => $lot->status?->value,
                'eligible_flag' => $lot->eligible_flag,
            ];

            $lot->update([
                'quantity' => $newQuantity,
                'status' => $newQuantity < 0 ? InventoryStatus::Observado : $lot->status,
                'eligible_flag' => $newQuantity > 0 && $lot->eligible_flag && $newQuantity >= 0,
                'block_reason' => $newQuantity < 0 ? 'Inconsistencia de stock registrada por ajuste.' : $lot->block_reason,
            ]);

            $record = InventoryAdjustment::create([
                'inventory_lot_id' => $lot->id,
                'adjustment_type' => $data['adjustment_type'],
                'quantity_adjustment' => $adjustment,
                'reason' => $data['reason'],
                'evidence_path' => $evidencePath,
                'responsible_user_id' => $userId,
                'adjusted_at' => $data['adjusted_at'],
            ]);

            $this->auditLogger->record('inventory.adjustment.created', $record, $before, [
                'inventory_lot_id' => $lot->id,
                'quantity_adjustment' => $adjustment,
                'quantity_after' => $newQuantity,
                'adjustment_type' => $data['adjustment_type'],
                'reason' => $data['reason'],
                'inconsistency' => $newQuantity < 0,
            ]);

            return $record;
        });
    }
}
