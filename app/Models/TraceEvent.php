<?php

namespace App\Models;

use Database\Factories\TraceEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TraceEvent extends Model
{
    /** @use HasFactory<TraceEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'trace_code',
        'traceable_type',
        'traceable_id',
        'surgery_case_id',
        'user_id',
        'event_type',
        'context',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function traceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function surgeryCase(): BelongsTo
    {
        return $this->belongsTo(SurgeryCase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'Sistema',
        ]);
    }
}
