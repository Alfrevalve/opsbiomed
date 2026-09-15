<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogImport extends Model
{
    protected $fillable = [
        'original_filename',
        'disk',
        'stored_path',
        'checksum',
        'status',
        'uploaded_by',
        'total_rows',
        'products_detected',
        'lots_detected',
        'critical_errors',
        'warnings',
        'inconsistencies',
        'na_accepted',
        'na_blocked',
        'stock_by_warehouse',
        'error_summary',
        'committed_at',
        'replaced_at',
    ];

    protected function casts(): array
    {
        return [
            'stock_by_warehouse' => 'array',
            'error_summary' => 'array',
            'committed_at' => 'datetime',
            'replaced_at' => 'datetime',
            'inconsistencies' => 'integer',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(CatalogImportRow::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
