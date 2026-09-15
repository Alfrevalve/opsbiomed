<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'inventory_lot_id',
        'adjustment_type',
        'quantity_adjustment',
        'reason',
        'evidence_path',
        'responsible_user_id',
        'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_adjustment' => 'integer',
            'adjusted_at' => 'datetime',
        ];
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
