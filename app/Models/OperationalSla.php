<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalSla extends Model
{
    protected $fillable = [
        'code',
        'name',
        'module',
        'trigger_event',
        'target_minutes',
        'time_unit',
        'priority',
        'responsible_roles',
        'schedule_rules',
        'metadata',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'target_minutes' => 'integer',
            'responsible_roles' => 'array',
            'schedule_rules' => 'array',
            'metadata' => 'array',
            'active' => 'boolean',
        ];
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(OperationalAlert::class);
    }
}
