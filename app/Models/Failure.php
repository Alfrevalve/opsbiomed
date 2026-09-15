<?php

namespace App\Models;

use App\Models\Concerns\HasTraceCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Failure extends Model
{
    use HasTraceCode;

    protected $fillable = [
        'case_id',
        'inventory_lot_id',
        'product_id',
        'severity',
        'failure_type',
        'occurrence_moment',
        'status',
        'preventive_block',
        'description',
        'action_taken',
        'evidence_reference',
        'reported_by',
        'responsible_technical_id',
        'diagnosis',
        'probable_cause',
        'corrective_action',
        'requires_supplier',
        'requires_replacement',
        'reviewed_at',
        'reviewed_by',
        'released_at',
        'released_by',
        'release_notes',
        'retired_at',
        'retired_by',
        'retirement_reason',
        'retirement_evidence',
    ];

    protected function casts(): array
    {
        return [
            'preventive_block' => 'boolean',
            'requires_supplier' => 'boolean',
            'requires_replacement' => 'boolean',
            'reviewed_at' => 'datetime',
            'released_at' => 'datetime',
            'retired_at' => 'datetime',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function responsibleTechnical(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_technical_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(DocumentEvidence::class, 'documentable');
    }
}
