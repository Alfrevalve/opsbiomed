<?php

namespace App\Models;

use Database\Factories\CaseResourceAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseResourceAssignment extends Model
{
    /** @use HasFactory<CaseResourceAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'surgery_case_id',
        'inventory_lot_id',
        'resource_type',
        'assigned_by',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function surgeryCase(): BelongsTo
    {
        return $this->belongsTo(SurgeryCase::class);
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
