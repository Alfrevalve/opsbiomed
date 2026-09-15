<?php

namespace App\Services\Inventory;

use App\Enums\InventoryStatus;
use App\Models\InventoryLot;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

class InventoryLotService
{
    public function __construct(
        private readonly StockEligibilityService $stockEligibility,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InventoryLot $inventoryLot, array $data): InventoryLot
    {
        return DB::transaction(function () use ($inventoryLot, $data): InventoryLot {
            $lot = InventoryLot::query()
                ->with(['product', 'warehouse'])
                ->lockForUpdate()
                ->findOrFail($inventoryLot->id);

            $before = $this->snapshot($lot);
            $activeReservationExists = $lot->reservations()->where('status', 'active')->exists();
            $criticalFields = ['status', 'detail_expiry', 'regulatory_expiry', 'classification', 'expiry_required'];

            if ($activeReservationExists && collect($criticalFields)->contains(
                fn (string $field): bool => array_key_exists($field, $data) && $this->fieldChanged($lot, $field, $data[$field]),
            )) {
                throw new DomainException('El lote tiene una reserva activa y no permite editar campos criticos.');
            }

            $status = array_key_exists('status', $data)
                ? InventoryStatus::from($data['status'])
                : $lot->status;
            $expiry = array_key_exists('detail_expiry', $data) ? $data['detail_expiry'] : $lot->expiry?->toDateString();
            $expiryRequired = array_key_exists('expiry_required', $data)
                ? (bool) $data['expiry_required']
                : (bool) $lot->product?->expiry_required;

            if ($expiryRequired && blank($expiry)) {
                throw new DomainException('Un producto clasificado como consumible debe tener vencimiento de lote.');
            }

            if ($status === InventoryStatus::Apto && $this->requiresTechnicalRelease($lot, $expiry)) {
                if (! auth()->user()?->can('inventory.release')) {
                    throw new DomainException('Se requiere liberacion tecnica para volver disponible un lote vencido o con falla preventiva.');
                }
            }

            $productData = array_filter([
                'name' => $data['name'] ?? null,
                'subfamily' => $data['subfamily'] ?? null,
                'regulatory_record' => $data['regulatory_record'] ?? null,
                'regulatory_expiry' => $data['regulatory_expiry'] ?? null,
                'classification' => $data['classification'] ?? null,
                'expiry_required' => array_key_exists('expiry_required', $data) ? (bool) $data['expiry_required'] : null,
            ], fn (mixed $value, string $key): bool => array_key_exists($key, $data), ARRAY_FILTER_USE_BOTH);

            $lotData = array_filter([
                'expiry' => $expiry,
                'location' => $data['location'] ?? null,
                'status' => $status,
                'block_reason' => $data['block_reason'] ?? null,
                'observations' => $data['observations'] ?? null,
            ], fn (mixed $value, string $key): bool => array_key_exists($key, $data) || $key === 'status', ARRAY_FILTER_USE_BOTH);

            if ($status !== InventoryStatus::Apto && array_key_exists('change_reason', $data) && blank($lotData['block_reason'] ?? null)) {
                $lotData['block_reason'] = $data['change_reason'];
            }

            $lot->product->update($productData);
            $lot->update($lotData);
            $lot->refresh()->load(['product', 'warehouse']);
            $lot->update([
                'eligible_flag' => $this->stockEligibility->isEligible($lot),
            ]);
            $lot->refresh()->load(['product', 'warehouse']);

            $after = $this->snapshot($lot);
            $this->auditLogger->record('inventory.lot.updated', $lot, $before, $after + [
                'change_reason' => $data['change_reason'] ?? null,
            ]);

            return $lot;
        });
    }

    private function requiresTechnicalRelease(InventoryLot $lot, ?string $expiry): bool
    {
        $expired = $expiry !== null && $expiry < today()->toDateString();

        return $expired || $this->hasOpenPreventiveFailure($lot->id);
    }

    private function hasOpenPreventiveFailure(int $lotId): bool
    {
        return DB::table('failures')
            ->where('inventory_lot_id', $lotId)
            ->where('preventive_block', true)
            ->whereIn('status', ['reportada', 'bloqueada', 'en_revision', 'pendiente_repuesto'])
            ->exists();
    }

    private function fieldChanged(InventoryLot $lot, string $field, mixed $value): bool
    {
        return match ($field) {
            'status' => $lot->status?->value !== $value,
            'detail_expiry' => $lot->expiry?->toDateString() !== $value,
            'regulatory_expiry' => $lot->product?->regulatory_expiry?->toDateString() !== $value,
            'classification' => $lot->product?->classification !== $value,
            'expiry_required' => (bool) $lot->product?->expiry_required !== (bool) $value,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(InventoryLot $lot): array
    {
        return [
            'lot' => [
                'id' => $lot->id,
                'lot' => $lot->lot,
                'serial' => $lot->serial,
                'expiry' => $lot->expiry?->toDateString(),
                'warehouse_id' => $lot->warehouse_id,
                'location' => $lot->location,
                'quantity' => $lot->quantity,
                'status' => $lot->status?->value,
                'block_reason' => $lot->block_reason,
                'eligible_flag' => $lot->eligible_flag,
                'observations' => $lot->observations,
            ],
            'product' => [
                'id' => $lot->product?->id,
                'product_code' => $lot->product?->product_code,
                'name' => $lot->product?->name,
                'subfamily' => $lot->product?->subfamily,
                'regulatory_record' => $lot->product?->regulatory_record,
                'regulatory_expiry' => $lot->product?->regulatory_expiry?->toDateString(),
                'classification' => $lot->product?->classification,
                'expiry_required' => $lot->product?->expiry_required,
            ],
        ];
    }
}
