<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentEvidence extends Model
{
    use SoftDeletes;

    public const BILLING_DOCUMENT_TYPES = ['orden_compra', 'factura', 'boleta', 'solicitud_pago', 'comprobante_pago'];

    public const APPROVAL_DOCUMENT_TYPES = ['aprobacion_costo_cero'];

    protected $table = 'document_evidences';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'document_type',
        'title',
        'description',
        'file_path',
        'link_url',
        'mime_type',
        'file_size',
        'uploaded_by',
        'document_date',
        'is_required',
        'status',
        'validation_observations',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'document_date' => 'date',
            'is_required' => 'boolean',
            'validated_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->can('documents.view')) {
            return $query->whereRaw('1 = 0');
        }

        if (! $user->can('billing.view')) {
            $query->where(function (Builder $query): void {
                $query->whereNotIn('document_type', self::BILLING_DOCUMENT_TYPES)
                    ->where(function (Builder $query): void {
                        $query->whereNull('documentable_type')
                            ->orWhere('documentable_type', '!=', BillingRecord::class);
                    });
            });
        }

        if (! $user->can('approvals.approve')) {
            $query->where(function (Builder $query): void {
                $query->whereNotIn('document_type', self::APPROVAL_DOCUMENT_TYPES)
                    ->where(function (Builder $query): void {
                        $query->whereNull('documentable_type')
                            ->orWhere('documentable_type', '!=', Approval::class);
                    });
            });
        }

        return $query;
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->can('documents.view')
            && ($user->can('billing.view') || (! in_array($this->document_type, self::BILLING_DOCUMENT_TYPES, true)
                && $this->documentable_type !== BillingRecord::class))
            && ($user->can('approvals.approve') || (! in_array($this->document_type, self::APPROVAL_DOCUMENT_TYPES, true)
                && $this->documentable_type !== Approval::class));
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
