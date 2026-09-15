<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseMaterialUsed extends Model
{
    protected $table = 'case_materials_used';

    protected $fillable = [
        'case_id',
        'reservation_id',
        'inventory_lot_id',
        'reserved_qty',
        'opened_qty',
        'used_qty',
        'unused_opened_qty',
        'returned_qty',
        'failure_qty',
        'difference_qty',
        'difference_reason',
        'evidence_path',
        'evidence_description',
        'evidence_reference',
        'failure_description',
        'unit_price',
        'minimum_unit_price',
        'price_below_minimum',
        'subtotal',
        'cost_zero',
        'cost_zero_reason',
        'requires_approval',
        'notes',
        'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'reserved_qty' => 'integer',
            'opened_qty' => 'integer',
            'used_qty' => 'integer',
            'unused_opened_qty' => 'integer',
            'returned_qty' => 'integer',
            'failure_qty' => 'integer',
            'difference_qty' => 'integer',
            'unit_price' => 'decimal:2',
            'minimum_unit_price' => 'decimal:2',
            'price_below_minimum' => 'boolean',
            'subtotal' => 'decimal:2',
            'cost_zero' => 'boolean',
            'requires_approval' => 'boolean',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurgeryCase::class, 'case_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
