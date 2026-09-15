<?php

namespace App\Services\Inventory;

use App\Enums\InventoryStatus;
use App\Enums\WarehouseType;
use App\Models\InventoryLot;
use App\Models\KitRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StockEligibilityService
{
    public function eligibleQuantityForRule(KitRule $rule): int
    {
        return $this->queryForRule($rule)
            ->get()
            ->sum(fn (InventoryLot $lot): int => $this->netAvailableForLot($lot));
    }

    public function queryForRule(KitRule $rule): Builder
    {
        return $this->eligibleLotsQuery()
            ->when(
                $rule->length_cm === null,
                fn (Builder $query): Builder => $query->whereNull('products.length_cm'),
                fn (Builder $query): Builder => $query->where('products.length_cm', $rule->length_cm),
            )
            ->when(
                $rule->diameter_mm === null,
                fn (Builder $query): Builder => $query->whereNull('products.diameter_mm'),
                fn (Builder $query): Builder => $query->where('products.diameter_mm', $rule->diameter_mm),
            )
            ->where('products.cut_type', $rule->cut_type)
            ->where('products.component_type', $rule->component_type);
    }

    public function eligibleLotsQuery(bool $includeYsanSupport = false): Builder
    {
        return InventoryLot::query()
            ->select('inventory_lots.*')
            ->join('products', 'products.id', '=', 'inventory_lots.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_lots.warehouse_id')
            ->where('inventory_lots.eligible_flag', true)
            ->where('inventory_lots.status', InventoryStatus::Apto->value)
            ->where('inventory_lots.quantity', '>', 0)
            ->where(function (Builder $query): void {
                $query->whereNull('inventory_lots.expiry')
                    ->orWhere('inventory_lots.expiry', '>=', today()->toDateString());
            })
            ->where('products.active', true)
            ->where(function (Builder $query): void {
                $query->where('products.expiry_required', false)
                    ->orWhereNotNull('inventory_lots.expiry');
            })
            ->where('warehouses.active', true)
            ->when(
                $includeYsanSupport,
                fn (Builder $query): Builder => $query->where(function (Builder $warehouseQuery): void {
                    $warehouseQuery->where('warehouses.counts_as_immediate', true)
                        ->orWhere('warehouses.type', WarehouseType::Ysan->value);
                }),
                fn (Builder $query): Builder => $query->where('warehouses.counts_as_immediate', true),
            )
            ->when(
                $includeYsanSupport,
                fn (Builder $query): Builder => $query->whereNotIn('warehouses.type', [WarehouseType::Desvalorizado->value]),
                fn (Builder $query): Builder => $query->whereNotIn('warehouses.type', [WarehouseType::Ysan->value, WarehouseType::Desvalorizado->value]),
            )
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('failures')
                    ->whereColumn('failures.inventory_lot_id', 'inventory_lots.id')
                    ->where('failures.preventive_block', true)
                    ->whereIn('failures.status', ['reportada', 'bloqueada', 'en_revision', 'pendiente_repuesto']);
            });
    }

    public function activeReservedQuantity(InventoryLot|int $lot): int
    {
        $lotId = $lot instanceof InventoryLot ? $lot->getKey() : $lot;

        return (int) DB::table('reservations')
            ->where('inventory_lot_id', $lotId)
            ->where('status', 'active')
            ->sum('quantity');
    }

    public function netAvailableForLot(InventoryLot $lot): int
    {
        if (! $this->isEligible($lot)) {
            return 0;
        }

        return max(0, (int) $lot->quantity - $this->activeReservedQuantity($lot));
    }

    public function isEligible(InventoryLot $lot): bool
    {
        $lot->loadMissing(['product', 'warehouse']);

        return $lot->eligible_flag
            && $lot->status === InventoryStatus::Apto
            && (int) $lot->quantity > 0
            && (! $lot->product?->expiry_required || $lot->expiry !== null)
            && (! $lot->expiry || ! $lot->expiry->isBefore(today()))
            && $lot->product?->active === true
            && $lot->warehouse?->active === true
            && $lot->warehouse?->counts_as_immediate === true
            && ! in_array($lot->warehouse?->type?->value, [WarehouseType::Ysan->value, WarehouseType::Desvalorizado->value], true)
            && ! $this->hasPreventiveFailureBlock($lot->getKey());
    }

    private function hasPreventiveFailureBlock(int $lotId): bool
    {
        return DB::table('failures')
            ->where('inventory_lot_id', $lotId)
            ->where('preventive_block', true)
            ->whereIn('status', ['reportada', 'bloqueada', 'en_revision', 'pendiente_repuesto'])
            ->exists();
    }
}
