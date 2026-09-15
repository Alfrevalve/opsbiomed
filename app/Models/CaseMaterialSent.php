<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseMaterialSent extends Model
{
    protected $table = 'case_materials_sent';

    protected $fillable = [
        'case_id',
        'reservation_id',
        'inventory_lot_id',
        'quantity',
        'guide_number',
        'sent_at',
        'sent_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sent_at' => 'datetime',
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

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
