<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Models\Concerns\HasTraceCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SurgeryCase extends Model
{
    use HasTraceCode;

    protected $fillable = [
        'case_code',
        'status',
        'institution_id',
        'doctor_id',
        'patient_id',
        'surgery_type_id',
        'scheduled_at',
        'priority',
        'procedure_name',
        'request_origin',
        'commercial_condition',
        'notes',
        'created_by',
        'assigned_instrumentist_id',
        'assigned_by',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'scheduled_at' => 'datetime',
            'assigned_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function surgeryType(): BelongsTo
    {
        return $this->belongsTo(SurgeryType::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'case_id');
    }

    public function materialsSent(): HasMany
    {
        return $this->hasMany(CaseMaterialSent::class, 'case_id');
    }

    public function preparation(): HasOne
    {
        return $this->hasOne(CasePreparation::class, 'case_id');
    }

    public function materialsUsed(): HasMany
    {
        return $this->hasMany(CaseMaterialUsed::class, 'case_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(CaseReturn::class, 'case_id');
    }

    public function failures(): HasMany
    {
        return $this->hasMany(Failure::class, 'case_id');
    }

    public function valuation(): HasOne
    {
        return $this->hasOne(CaseValuation::class, 'case_id');
    }

    public function billingRecord(): HasOne
    {
        return $this->hasOne(BillingRecord::class, 'case_id');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(CaseReconciliation::class, 'case_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(DocumentEvidence::class, 'documentable');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedInstrumentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_instrumentist_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function resourceAssignments(): HasMany
    {
        return $this->hasMany(CaseResourceAssignment::class);
    }
}
