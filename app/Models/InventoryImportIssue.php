<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryImportIssue extends Model
{
    protected $fillable = [
        'catalog_import_id',
        'catalog_import_row_id',
        'product_id',
        'warehouse_id',
        'product_code',
        'product_name',
        'lot',
        'serial',
        'warehouse_key',
        'warehouse_name',
        'quantity',
        'issue_type',
        'message',
        'status',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function catalogImport(): BelongsTo
    {
        return $this->belongsTo(CatalogImport::class);
    }

    public function catalogImportRow(): BelongsTo
    {
        return $this->belongsTo(CatalogImportRow::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
