<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use App\Models\Concerns\HasTraceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InventoryLot extends Model
{
    use HasTraceCode;

    protected $fillable = [
        'product_id',
        'catalog_import_id',
        'lot',
        'serial',
        'expiry',
        'warehouse_id',
        'location',
        'quantity',
        'status',
        'block_reason',
        'eligible_flag',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'expiry' => 'date',
            'status' => InventoryStatus::class,
            'eligible_flag' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    public function catalogImport(): BelongsTo
    {
        return $this->belongsTo(CatalogImport::class);
    }

    public function failures(): HasMany
    {
        return $this->hasMany(Failure::class, 'inventory_lot_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(CaseReturn::class, 'inventory_lot_id');
    }

    public function caseResourceAssignments(): HasMany
    {
        return $this->hasMany(CaseResourceAssignment::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(DocumentEvidence::class, 'documentable');
    }

    public function scopeEligibleImmediate(Builder $query): Builder
    {
        return $query->where('eligible_flag', true)
            ->where('status', InventoryStatus::Apto)
            ->where('quantity', '>', 0)
            ->whereHas('warehouse', fn (Builder $warehouse) => $warehouse->where('counts_as_immediate', true));
    }
}
