<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseReconciliation extends Model
{
    protected $fillable = [
        'case_id',
        'status',
        'total_reserved',
        'total_used',
        'total_returned',
        'total_unused_opened',
        'total_failure',
        'total_difference',
        'observations',
        'completed_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_reserved' => 'integer',
            'total_used' => 'integer',
            'total_returned' => 'integer',
            'total_unused_opened' => 'integer',
            'total_failure' => 'integer',
            'total_difference' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurgeryCase::class, 'case_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
