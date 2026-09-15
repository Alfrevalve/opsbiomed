<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'product_line',
        'family',
        'subfamily',
        'product_code',
        'normalized_code',
        'name',
        'regulatory_record',
        'regulatory_expiry',
        'length_cm',
        'diameter_mm',
        'cut_type',
        'component_type',
        'classification',
        'expiry_required',
        'tracking_type',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'regulatory_expiry' => 'date',
            'length_cm' => 'decimal:2',
            'diameter_mm' => 'decimal:2',
            'expiry_required' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function inventoryLots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }
}
