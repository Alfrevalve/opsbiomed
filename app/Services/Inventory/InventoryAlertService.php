<?php

namespace App\Services\Inventory;

use App\Enums\InventoryStatus;
use App\Enums\WarehouseType;
use App\Models\InventoryImportIssue;
use App\Models\InventoryLot;

class InventoryAlertService
{
    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $pendingImportIssues = InventoryImportIssue::query()
            ->where('status', 'pending')
            ->whereHas('catalogImport', fn ($query) => $query->where('status', 'committed'));

        return [
            'blocked' => InventoryLot::query()->where('status', InventoryStatus::Bloqueado->value)->count(),
            'quarantine' => InventoryLot::query()->where('status', InventoryStatus::Cuarentena->value)->count(),
            'preventive_failure' => InventoryLot::query()
                ->where(function ($query): void {
                    $query->where('status', InventoryStatus::FallaPreventiva->value)
                        ->orWhereExists(function ($failureQuery): void {
                            $failureQuery->selectRaw('1')
                                ->from('failures')
                                ->whereColumn('failures.inventory_lot_id', 'inventory_lots.id')
                                ->where('failures.preventive_block', true)
                                ->whereNotIn('failures.status', ['liberada', 'dada_de_baja', 'cerrada']);
                        });
                })
                ->count(),
            'expired' => InventoryLot::query()
                ->where(function ($query): void {
                    $query->where('status', InventoryStatus::Vencido->value)
                        ->orWhere(function ($expiryQuery): void {
                            $expiryQuery->whereNotNull('expiry')
                                ->where('expiry', '<', today()->toDateString());
                        });
                })
                ->count(),
            'expiring_soon' => InventoryLot::query()
                ->whereNotNull('expiry')
                ->whereBetween('expiry', [today()->toDateString(), today()->addDays(90)->toDateString()])
                ->where('quantity', '>', 0)
                ->count(),
            'negative' => InventoryLot::query()->where('quantity', '<', 0)->count(),
            'devalued' => InventoryLot::query()
                ->where(function ($query): void {
                    $query->where('status', InventoryStatus::Desvalorizado->value)
                        ->orWhereHas('warehouse', fn ($warehouseQuery) => $warehouseQuery->where('type', WarehouseType::Desvalorizado->value));
                })
                ->count(),
            'import_inconsistencies_pending' => (clone $pendingImportIssues)->count(),
            'negative_import_quantities' => (clone $pendingImportIssues)->count(),
        ];
    }
}
