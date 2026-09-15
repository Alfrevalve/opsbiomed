<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentEvidence extends Model
{
    use SoftDeletes;

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
