<?php

use App\Http\Controllers\Api\OpsDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::get('/dashboard/ops', OpsDashboardController::class);
});
