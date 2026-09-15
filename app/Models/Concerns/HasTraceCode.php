<?php

namespace App\Models\Concerns;

use App\Services\Traceability\TraceCodeService;
use Illuminate\Database\Eloquent\Model;

trait HasTraceCode
{
    public static function bootHasTraceCode(): void
    {
        static::created(function (Model $model): void {
            if (blank($model->getAttribute('trace_code'))) {
                app(TraceCodeService::class)->ensureFor($model);
            }
        });
    }
}
