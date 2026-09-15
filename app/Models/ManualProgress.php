<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualProgress extends Model
{
    protected $table = 'manual_progress';

    protected $fillable = [
        'user_id',
        'article_id',
        'completed_at',
        'last_viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(ManualArticle::class, 'article_id');
    }
}
