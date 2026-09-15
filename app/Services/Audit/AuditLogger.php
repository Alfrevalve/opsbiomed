<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function record(string $action, ?Model $model = null, array $before = [], array $after = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
