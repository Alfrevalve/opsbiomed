<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OperationalAlert extends Model
{
    public const OPEN_STATUSES = ['open', 'acknowledged'];

    protected $fillable = [
        'operational_sla_id',
        'alertable_type',
        'alertable_id',
        'alert_code',
        'module',
        'title',
        'description',
        'priority',
        'status',
        'responsible_role',
        'responsible_user_id',
        'due_at',
        'detected_at',
        'acknowledged_at',
        'resolved_at',
        'dismissed_at',
        'resolved_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function sla(): BelongsTo
    {
        return $this->belongsTo(OperationalSla::class, 'operational_sla_id');
    }

    public function alertable(): MorphTo
    {
        return $this->morphTo();
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOverdue(): bool
    {
        return $this->due_at?->isPast() === true && in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isDueSoon(int $hours = 24): bool
    {
        return $this->due_at !== null
            && $this->due_at->isFuture()
            && $this->due_at->lessThanOrEqualTo(now()->addHours($hours))
            && in_array($this->status, self::OPEN_STATUSES, true);
    }
}
