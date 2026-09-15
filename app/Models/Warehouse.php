<?php

namespace App\Models;

use App\Enums\WarehouseType;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['name', 'type', 'lead_time_hours', 'counts_as_immediate', 'active'];

    protected function casts(): array
    {
        return [
            'type' => WarehouseType::class,
            'counts_as_immediate' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
