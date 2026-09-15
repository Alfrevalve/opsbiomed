<?php

namespace App\Models;

use App\Models\Concerns\HasTraceCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CaseReturn extends Model
{
    use HasTraceCode;

    protected $table = 'case_returns';

    protected $fillable = [
        'case_id',
        'inventory_lot_id',
        'returned_qty',
        'condition',
        'inspected_by',
        'inspected_at',
        'inspection_result',
        'inspection_observations',
        'inspection_evidence_reference',
        'inspection_responsible_id',
        'inspection_date',
        'technical_failure_id',
        'non_reusable_reason',
    ];

    protected function casts(): array
    {
        return [
            'returned_qty' => 'integer',
            'inspected_at' => 'datetime',
            'inspection_date' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurgeryCase::class, 'case_id');
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function inspectionResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspection_responsible_id');
    }

    public function technicalFailure(): BelongsTo
    {
        return $this->belongsTo(Failure::class, 'technical_failure_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(DocumentEvidence::class, 'documentable');
    }
}
