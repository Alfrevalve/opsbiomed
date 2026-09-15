<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogImportRow extends Model
{
    protected $fillable = [
        'catalog_import_id',
        'row_number',
        'raw_data',
        'product_line',
        'family',
        'subfamily',
        'product_code',
        'product_name',
        'classification',
        'expiry_required',
        'expiry_is_na',
        'regulatory_record',
        'regulatory_expiry',
        'lot',
        'serial',
        'detail_expiry',
        'location',
        'warehouse_quantities',
        'total_quantity',
        'status',
        'errors',
        'warnings',
        'inconsistencies',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'regulatory_expiry' => 'date',
            'detail_expiry' => 'date',
            'expiry_required' => 'boolean',
            'expiry_is_na' => 'boolean',
            'warehouse_quantities' => 'array',
            'errors' => 'array',
            'warnings' => 'array',
            'inconsistencies' => 'array',
        ];
    }

    public function catalogImport(): BelongsTo
    {
        return $this->belongsTo(CatalogImport::class);
    }
}
