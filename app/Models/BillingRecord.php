<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BillingRecord extends Model
{
    protected $fillable = [
        'case_id',
        'amount',
        'amount_paid',
        'currency',
        'invoice_status',
        'purchase_order',
        'invoice_number',
        'invoice_date',
        'due_date',
        'payment_status',
        'debt_days',
        'no_billing_reason',
        'observations',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'debt_days' => 'integer',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurgeryCase::class, 'case_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(DocumentEvidence::class, 'documentable');
    }

    public function getBalanceAttribute(): float
    {
        return round(max(0, (float) $this->amount - (float) $this->amount_paid), 2);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->balance > 0
            && $this->due_date?->isBefore(today()) === true
            && ! in_array($this->invoice_status, ['no_facturable', 'costo_cero_pendiente_aprobacion', 'costo_cero_aprobado'], true);
    }
}
