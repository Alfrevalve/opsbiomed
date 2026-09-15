<?php

namespace App\Models;

use Database\Factories\AiUsageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'module',
        'action',
        'input_type',
        'contains_personal_data',
        'contains_health_data',
        'ai_provider',
        'human_review_required',
        'human_reviewed_by',
        'human_reviewed_at',
        'human_review_outcome',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'contains_personal_data' => 'boolean',
            'contains_health_data' => 'boolean',
            'human_review_required' => 'boolean',
            'human_reviewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault([
            'name' => 'Sistema',
        ]);
    }

    public function humanReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'human_reviewed_by')->withDefault([
            'name' => 'Sistema',
        ]);
    }
}
