<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseValuationLine extends Model
{
    protected $fillable = [
        'case_valuation_id',
        'case_id',
        'reservation_id',
        'product_id',
        'inventory_lot_id',
        'quantity_used',
        'unit_price',
        'minimum_unit_price',
        'price_below_minimum',
        'subtotal',
        'cost_zero',
        'cost_zero_reason',
        'requires_approval',
    ];

    protected function casts(): array
    {
        return [
            'quantity_used' => 'integer',
            'unit_price' => 'decimal:2',
            'minimum_unit_price' => 'decimal:2',
            'price_below_minimum' => 'boolean',
            'subtotal' => 'decimal:2',
            'cost_zero' => 'boolean',
            'requires_approval' => 'boolean',
        ];
    }

    public function valuation(): BelongsTo
    {
        return $this->belongsTo(CaseValuation::class, 'case_valuation_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurgeryCase::class, 'case_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }
}
