<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualArticle extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'summary',
        'level',
        'body',
        'estimated_minutes',
        'roles_json',
        'keywords_json',
        'prerequisites_json',
        'steps_json',
        'required_fields_json',
        'expected_result',
        'common_errors_json',
        'blocked_action',
        'escalation_role',
        'module',
        'permission',
        'route_name',
        'sort_order',
        'active',
        'version',
        'published_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estimated_minutes' => 'integer',
            'roles_json' => 'array',
            'keywords_json' => 'array',
            'prerequisites_json' => 'array',
            'steps_json' => 'array',
            'required_fields_json' => 'array',
            'common_errors_json' => 'array',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ManualCategory::class, 'category_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(ManualProgress::class, 'article_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(function (Builder $publishedQuery): void {
                $publishedQuery->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function isVisibleTo(User $user): bool
    {
        $roles = $this->roles_json ?? [];
        $hasRole = $roles === [] || $user->hasAnyRole($roles);
        $hasPermission = ! $this->permission || $user->can($this->permission);

        return $hasRole && $hasPermission;
    }

    public function isRecommendedFor(User $user): bool
    {
        $roles = $this->roles_json ?? [];

        if ($roles === []) {
            return in_array($this->level, ['basico', 'operativo'], true);
        }

        return $user->hasAnyRole($roles);
    }
}
