<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationReleaseOperation extends Model
{
    protected $fillable = [
        'idempotency_key',
        'operation_type',
        'case_id',
        'reservation_id',
        'user_id',
        'reason',
        'status',
        'result',
    ];

    protected function casts(): array
    {
        return ['result' => 'array'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurgeryCase::class, 'case_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
