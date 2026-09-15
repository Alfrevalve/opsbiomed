<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOperationalAlertStatusRequest;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\BillingRecord;
use App\Models\CaseReturn;
use App\Models\DocumentEvidence;
use App\Models\Failure;
use App\Models\OperationalAlert;
use App\Models\SurgeryCase;
use App\Models\User;
use App\Services\Operations\OperationalAlertService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class OperationalAlertController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', OperationalAlert::class);
        $filters = $request->validate([
            'module' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'in:critical,high,medium,low'],
            'status' => ['nullable', 'in:open,acknowledged,resolved,dismissed,expired'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_role' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = OperationalAlert::query()
            ->with(['sla', 'responsibleUser'])
            ->when($filters['module'] ?? null, fn ($query, string $module) => $query->where('module', $module))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['responsible_user_id'] ?? null, fn ($query, int $userId) => $query->where('responsible_user_id', $userId))
            ->when($filters['responsible_role'] ?? null, fn ($query, string $role) => $query->where('responsible_role', $role))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('detected_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('detected_at', '<=', $date))
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->latest('detected_at');

        $alerts = $query->paginate(25)->withQueryString();
        $openStatuses = OperationalAlert::OPEN_STATUSES;

        return view('alerts.index', [
            'alerts' => $alerts,
            'filters' => $filters,
            'modules' => OperationalAlert::query()->distinct()->orderBy('module')->pluck('module'),
            'responsibleUsers' => User::query()->whereIn('id', OperationalAlert::query()->whereNotNull('responsible_user_id')->distinct()->pluck('responsible_user_id'))->orderBy('name')->get(['id', 'name']),
            'responsibleRoles' => OperationalAlert::query()->whereNotNull('responsible_role')->distinct()->orderBy('responsible_role')->pluck('responsible_role'),
            'counters' => [
                'open' => OperationalAlert::query()->whereIn('status', $openStatuses)->count(),
                'critical' => OperationalAlert::query()->whereIn('status', $openStatuses)->where('priority', 'critical')->count(),
                'expired' => OperationalAlert::query()->where('status', 'expired')->orWhere(function ($query): void {
                    $query->whereIn('status', OperationalAlert::OPEN_STATUSES)->where('due_at', '<', now());
                })->count(),
                'due_soon' => OperationalAlert::query()->whereIn('status', $openStatuses)->whereBetween('due_at', [now(), now()->addHours(24)])->count(),
                'acknowledged' => OperationalAlert::query()->where('status', 'acknowledged')->count(),
                'resolved' => OperationalAlert::query()->where('status', 'resolved')->count(),
            ],
            'priorityLabels' => [
                'critical' => 'Crítica',
                'high' => 'Alta',
                'medium' => 'Media',
                'low' => 'Baja',
            ],
            'statusLabels' => [
                'open' => 'Abierta',
                'acknowledged' => 'Reconocida',
                'resolved' => 'Resuelta',
                'dismissed' => 'Descartada',
                'expired' => 'Vencida',
            ],
        ]);
    }

    public function show(OperationalAlert $alert): View
    {
        $this->authorize('view', $alert);
        $alert->load(['sla', 'responsibleUser', 'resolvedBy', 'alertable']);

        return view('alerts.show', [
            'alert' => $alert,
            'relatedUrl' => $this->relatedUrl($alert),
            'auditLogs' => AuditLog::query()->with('user')->where('auditable_type', OperationalAlert::class)->where('auditable_id', $alert->id)->latest()->limit(20)->get(),
            'priorityLabels' => ['critical' => 'Crítica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'],
            'statusLabels' => ['open' => 'Abierta', 'acknowledged' => 'Reconocida', 'resolved' => 'Resuelta', 'dismissed' => 'Descartada', 'expired' => 'Vencida'],
        ]);
    }

    public function acknowledge(UpdateOperationalAlertStatusRequest $request, OperationalAlert $alert, OperationalAlertService $service): RedirectResponse
    {
        $this->authorize('acknowledge', $alert);

        return $this->changeStatus($request, $alert, $service, 'acknowledge');
    }

    public function resolve(UpdateOperationalAlertStatusRequest $request, OperationalAlert $alert, OperationalAlertService $service): RedirectResponse
    {
        $this->authorize('resolve', $alert);

        return $this->changeStatus($request, $alert, $service, 'resolve');
    }

    public function dismiss(UpdateOperationalAlertStatusRequest $request, OperationalAlert $alert, OperationalAlertService $service): RedirectResponse
    {
        $this->authorize('dismiss', $alert);

        return $this->changeStatus($request, $alert, $service, 'dismiss');
    }

    private function changeStatus(
        UpdateOperationalAlertStatusRequest $request,
        OperationalAlert $alert,
        OperationalAlertService $service,
        string $action,
    ): RedirectResponse {
        try {
            $updated = match ($action) {
                'acknowledge' => $service->acknowledge($alert, $request->user(), $request->validated('note')),
                'resolve' => $service->resolve($alert, $request->user(), $request->validated('note')),
                default => $service->dismiss($alert, $request->user(), $request->validated('note')),
            };
        } catch (DomainException $exception) {
            return back()->withErrors(['alert' => $exception->getMessage()]);
        }

        return redirect()->route('alerts.show', $updated)->with('status', 'Alerta actualizada y auditada.');
    }

    private function relatedUrl(OperationalAlert $alert): ?string
    {
        $type = $alert->alertable_type;
        $entity = $alert->alertable;
        $user = auth()->user();

        return match ($type) {
            SurgeryCase::class => $entity && $user?->can('cases.view') && Route::has('cases.control') ? route('cases.control', $entity) : null,
            CaseReturn::class => $entity && $user?->can('returns.view') && Route::has('returns.show') ? route('returns.show', $entity) : null,
            Failure::class => $entity && $user?->can('failures.view') && Route::has('failures.show') ? route('failures.show', $entity) : null,
            DocumentEvidence::class => $entity && $user?->can('documents.view') && Route::has('documents.show') ? route('documents.show', $entity) : null,
            BillingRecord::class => $entity && $user?->can('billing.view') && Route::has('billing.show') ? route('billing.show', $entity->case) : null,
            Approval::class => $user?->can('approvals.approve') && Route::has('approvals.cost-zero.index') ? route('approvals.cost-zero.index') : null,
            default => null,
        };
    }
}
