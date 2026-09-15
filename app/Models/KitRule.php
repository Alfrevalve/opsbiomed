<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitRule extends Model
{
    protected $fillable = [
        'surgery_type_id',
        'length_cm',
        'diameter_mm',
        'cut_type',
        'component_type',
        'min_qty',
        'target_qty',
        'criticality',
        'required',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'length_cm' => 'decimal:2',
            'diameter_mm' => 'decimal:2',
            'required' => 'boolean',
        ];
    }

    public function surgeryType(): BelongsTo
    {
        return $this->belongsTo(SurgeryType::class);
    }
}
