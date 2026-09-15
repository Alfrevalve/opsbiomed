<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_id',
        'institution_id',
        'doctor_id',
        'unit_price',
        'minimum_price',
        'currency',
        'price_type',
        'active',
        'valid_from',
        'valid_until',
        'created_by',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'minimum_price' => 'decimal:2',
            'active' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
