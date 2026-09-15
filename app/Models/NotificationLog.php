<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'operational_alert_id',
        'user_id',
        'channel',
        'status',
        'attempts',
        'subject',
        'payload',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(OperationalAlert::class, 'operational_alert_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
