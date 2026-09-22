<?php

namespace App\Models;

use App\Models\Concerns\HasTraceCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use HasTraceCode;

    protected $fillable = ['case_id', 'inventory_lot_id', 'quantity', 'status', 'reserved_by', 'expires_at', 'idempotency_key'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurgeryCase::class, 'case_id');
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    public function reservedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    public function materialSent(): HasOne
    {
        return $this->hasOne(CaseMaterialSent::class);
    }
}
