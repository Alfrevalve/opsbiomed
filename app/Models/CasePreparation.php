<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CasePreparation extends Model
{
    protected $fillable = [
        'case_id',
        'institution_confirmed',
        'doctor_confirmed',
        'schedule_confirmed',
        'material_confirmed',
        'documents_confirmed',
        'guide_number',
        'delivery_evidence_reference',
        'notes',
        'prepared_by',
        'prepared_at',
        'dispatched_by',
        'dispatched_at',
    ];

    protected function casts(): array
    {
        return [
            'institution_confirmed' => 'boolean',
            'doctor_confirmed' => 'boolean',
            'schedule_confirmed' => 'boolean',
            'material_confirmed' => 'boolean',
            'documents_confirmed' => 'boolean',
            'prepared_at' => 'datetime',
            'dispatched_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurgeryCase::class, 'case_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }
}
