<?php

namespace App\Http\Controllers\Api;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\SurgeryCase;
use Illuminate\Http\JsonResponse;

class OpsDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        abort_unless(auth()->user()?->can('dashboard.view'), 403);

        $now = now();
        $closedStatuses = [
            CaseStatus::Cerrado->value,
            CaseStatus::Cerrada->value,
            CaseStatus::Facturacion->value,
            CaseStatus::Facturada->value,
            CaseStatus::Cancelado->value,
        ];

        return response()->json([
            'open_cases' => SurgeryCase::query()->whereNotIn('status', $closedStatuses)->count(),
            'today_cases' => SurgeryCase::query()->whereDate('scheduled_at', today())->count(),
            'upcoming_48_hours' => SurgeryCase::query()
                ->whereNotIn('status', $closedStatuses)
                ->whereBetween('scheduled_at', [$now, $now->copy()->addHours(48)])
                ->count(),
            'pending_closure' => SurgeryCase::query()->where('status', CaseStatus::PendienteCierre->value)->count(),
        ]);
    }
}
