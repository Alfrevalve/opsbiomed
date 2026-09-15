<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Approval extends Model
{
    protected $fillable = [
        'case_id',
        'valuation_id',
        'type',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'evidence',
        'reason',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurgeryCase::class, 'case_id');
    }

    public function valuation(): BelongsTo
    {
        return $this->belongsTo(CaseValuation::class, 'valuation_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(DocumentEvidence::class, 'documentable');
    }
}
