<?php

namespace App\Services\Inventory;

use App\Enums\InventoryStatus;
use App\Models\InventoryAdjustment;
use App\Models\InventoryLot;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InventoryAdjustmentService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(InventoryLot $inventoryLot, array $data, int $userId): InventoryAdjustment
    {
        $evidencePath = null;

        try {
            return DB::transaction(function () use ($inventoryLot, $data, $userId, &$evidencePath): InventoryAdjustment {
                $lot = InventoryLot::query()->lockForUpdate()->findOrFail($inventoryLot->id);
                $adjustment = (int) $data['quantity_adjustment'];
                $newQuantity = (int) $lot->quantity + $adjustment;
                $allowInconsistency = (bool) ($data['allow_inconsistency'] ?? false);
                $activeReservedQuantity = (int) DB::table('reservations')
                    ->where('inventory_lot_id', $lot->id)
                    ->where('status', 'active')
                    ->sum('quantity');

                if ($activeReservedQuantity > 0 && $newQuantity < $activeReservedQuantity) {
                    throw new DomainException('El ajuste dejaria el stock fisico por debajo de las reservas activas. Concilia primero la reserva.');
                }

                if ($newQuantity < 0 && (! $allowInconsistency || ! auth()->user()?->can('inventory.audit'))) {
                    throw new DomainException('El ajuste no puede dejar stock negativo sin registrar una inconsistencia autorizada.');
                }

                if (($data['evidence'] ?? null) instanceof UploadedFile) {
                    $storedEvidencePath = $data['evidence']->store('inventory-adjustments', 'private');

                    if (! is_string($storedEvidencePath)) {
                        throw new DomainException('No fue posible almacenar la evidencia privada del ajuste.');
                    }

                    $evidencePath = $storedEvidencePath;
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
        } catch (Throwable $exception) {
            $cleanupSucceeded = null;

            if ($evidencePath !== null) {
                try {
                    $disk = Storage::disk('private');
                    $disk->delete($evidencePath);
                    $cleanupSucceeded = ! $disk->exists($evidencePath);

                    if (! $cleanupSucceeded) {
                        Log::critical('Inventory adjustment evidence compensation failed.', [
                            'inventory_lot_id' => $inventoryLot->id,
                            'user_id' => $userId,
                            'error_type' => 'PrivateEvidenceDeleteFailed',
                        ]);
                    }
                } catch (Throwable $cleanupException) {
                    $cleanupSucceeded = false;
                    Log::critical('Inventory adjustment evidence compensation failed.', [
                        'inventory_lot_id' => $inventoryLot->id,
                        'user_id' => $userId,
                        'error_type' => $cleanupException::class,
                    ]);
                }
            }

            Log::warning('Inventory adjustment transaction failed.', [
                'inventory_lot_id' => $inventoryLot->id,
                'user_id' => $userId,
                'evidence_cleanup_succeeded' => $cleanupSucceeded,
                'error_type' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
