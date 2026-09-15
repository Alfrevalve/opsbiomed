<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Models\BillingRecord;
use App\Models\CaseMaterialUsed;
use App\Models\CaseReturn;
use App\Models\DocumentEvidence;
use App\Models\Failure;
use App\Models\InventoryLot;
use App\Models\OperationalAlert;
use App\Models\SurgeryCase;
use App\Services\Inventory\InventoryAlertService;
use App\Services\Inventory\ReplenishmentForecastService;
use App\Services\Inventory\StockRiskService;
use App\Services\Inventory\SurgeryCoverageService;
use App\Services\Operations\BillingService;
use App\Services\Operations\SurgeryCasePreparationService;
use App\Services\Operations\SurgeryScheduleService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(
        StockRiskService $stockRiskService,
        InventoryAlertService $inventoryAlertService,
        SurgeryCoverageService $coverageService,
        ReplenishmentForecastService $forecastService,
        BillingService $billingService,
        SurgeryCasePreparationService $preparationService,
        SurgeryScheduleService $scheduleService,
    ): View {
        $this->authorize('viewAny', SurgeryCase::class);

        $now = now();
        $next48Hours = $now->copy()->addHours(48);
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $activeStatuses = [
            CaseStatus::Cerrado->value,
            CaseStatus::Cerrada->value,
            CaseStatus::Facturacion->value,
            CaseStatus::Facturada->value,
            CaseStatus::Cancelado->value,
        ];

        $stockSummary = $stockRiskService->dashboardSummary();
        $coverageSummary = $coverageService->summary();
        $inventoryAlerts = $inventoryAlertService->summary();
        $billingService->refreshOverdueStatuses();
        $user = auth()->user();
        $canViewBilling = $user->can('billing.view');
        $canViewDocuments = $user->can('documents.view');
        $canViewInventory = $user->can('inventory.view');
        $canViewFailures = $user->can('failures.view');
        $canViewCommercial = $user->can('commercial.view');
        $canApprovals = $user->can('approvals.approve');
        $canViewSchedule = $user->can('schedule.view');
        $canViewAlerts = $user->can('alerts.view');
        $alertOpenStatuses = OperationalAlert::OPEN_STATUSES;
        $resolvedAlerts = $canViewAlerts
            ? OperationalAlert::query()->where('status', 'resolved')->whereNotNull('detected_at')->whereNotNull('resolved_at')->get(['detected_at', 'resolved_at'])
            : collect();
        $alertSummary = [
            'open' => $canViewAlerts ? OperationalAlert::query()->whereIn('status', $alertOpenStatuses)->count() : 0,
            'critical' => $canViewAlerts ? OperationalAlert::query()->whereIn('status', $alertOpenStatuses)->where('priority', 'critical')->count() : 0,
            'expired' => $canViewAlerts ? OperationalAlert::query()->where('status', 'expired')->count() : 0,
            'due_soon' => $canViewAlerts ? OperationalAlert::query()->whereIn('status', $alertOpenStatuses)->whereBetween('due_at', [now(), now()->addHours(24)])->count() : 0,
            'average_resolution_minutes' => $resolvedAlerts->isEmpty()
                ? 0
                : (int) round($resolvedAlerts->avg(fn (OperationalAlert $alert): int => $alert->detected_at->diffInMinutes($alert->resolved_at))),
            'by_responsible' => $canViewAlerts
                ? OperationalAlert::query()->whereIn('status', $alertOpenStatuses)->selectRaw("COALESCE(responsible_role, 'Sin asignar') as responsible, COUNT(*) as total")->groupBy('responsible_role')->orderByDesc('total')->limit(6)->get()
                : collect(),
        ];
        $canViewReturns = $user->can('returns.view') || $user->hasAnyRole([
            'Administrador',
            'Direccion Tecnica',
            'Almacen',
            'Jefe de Linea',
            'Gerencia',
            'Instrumentista',
        ]);
        $forecastSummary = $user->can('inventory.view') && $user->hasAnyRole([
            'Administrador',
            'Jefe de Linea',
            'Direccion Tecnica',
            'Almacen',
            'Gerencia',
        ]) ? $forecastService->summary() : null;
        $canPrepareCases = ($user->can('cases.update') || $user->can('cases.close') || $user->can('reservations.create'))
            && $user->hasAnyRole([
                'Administrador',
                'Jefe de Linea',
                'Programador Quirurgico',
                'Instrumentista',
                'Almacen',
                'Direccion Tecnica',
            ]);
        $preoperativePendingCases = $canPrepareCases
            ? $preparationService->upcomingPendingCases($now, $next48Hours)
            : collect();
        $agendaRelations = ['institution', 'doctor', 'surgeryType'];
        if ($canViewInventory) {
            $agendaRelations[] = 'reservations';
        }
        $agendaCases = SurgeryCase::query()
            ->with($agendaRelations)
            ->whereBetween('scheduled_at', [$now->copy()->startOfDay(), $next48Hours])
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get();
        $scheduleSummary = $canViewSchedule
            ? $scheduleService->forRange($now, $next48Hours)['summary']
            : null;
        $topInstitutions = $canViewCommercial
            ? SurgeryCase::query()
                ->with('institution:id,name')
                ->whereBetween('scheduled_at', [$monthStart, $monthEnd])
                ->whereNotNull('institution_id')
                ->where('status', '!=', CaseStatus::Cancelado->value)
                ->select('institution_id')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('institution_id')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
            : collect();
        $topDoctors = $canViewCommercial
            ? SurgeryCase::query()
                ->with('doctor:id,name')
                ->whereBetween('scheduled_at', [$monthStart, $monthEnd])
                ->whereNotNull('doctor_id')
                ->where('status', '!=', CaseStatus::Cancelado->value)
                ->select('doctor_id')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('doctor_id')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
            : collect();
        $openFailureStatuses = ['reportada', 'bloqueada', 'en_revision', 'pendiente_repuesto'];
        $failureTypeSummary = Failure::query()
            ->whereIn('status', $openFailureStatuses)
            ->selectRaw('failure_type, COUNT(*) as total')
            ->groupBy('failure_type')
            ->orderByDesc('total')
            ->get();
        $failureProductSummary = Failure::query()
            ->with(['product', 'inventoryLot.product'])
            ->whereIn('status', $openFailureStatuses)
            ->get()
            ->groupBy(fn (Failure $failure): string => (string) ($failure->product_id ?? $failure->inventoryLot?->product_id ?? 0))
            ->map(fn ($failures): array => [
                'label' => $failures->first()->product?->product_code ?? 'Producto no especificado',
                'total' => $failures->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->take(8);

        $documentableStatuses = ['cargado', 'validado'];
        $casesWithoutConsumptionEvidence = $canViewDocuments
            ? SurgeryCase::query()
                ->whereIn('status', [CaseStatus::Cerrado->value, CaseStatus::Cerrada->value])
                ->whereDoesntHave('documents', fn (Builder $query) => $query
                    ->whereIn('document_type', ['evidencia_consumo', 'hoja_consumo'])
                    ->whereIn('status', $documentableStatuses))
                ->count()
            : 0;
        $casesWithoutSignedDocument = $canViewDocuments
            ? SurgeryCase::query()
                ->whereIn('status', [CaseStatus::Cerrado->value, CaseStatus::Cerrada->value])
                ->whereDoesntHave('documents', fn (Builder $query) => $query
                    ->where('document_type', 'hoja_consumo')
                    ->whereIn('status', $documentableStatuses))
                ->count()
            : 0;
        $invoicesWithoutPurchaseOrder = $canViewDocuments && $canViewBilling
            ? BillingRecord::query()
                ->whereNotNull('invoice_number')
                ->where('invoice_number', '!=', '')
                ->where(function (Builder $query): void {
                    $query->whereNull('purchase_order')->orWhere('purchase_order', '');
                })
                ->count()
            : 0;
        $failuresWithoutEvidence = $canViewDocuments
            ? Failure::query()
                ->whereDoesntHave('documents', fn (Builder $query) => $query
                    ->whereIn('document_type', ['evidencia_falla', 'reporte_falla'])
                    ->whereIn('status', $documentableStatuses))
                ->count()
            : 0;
        $returnsWithoutInspectionEvidence = $canViewDocuments
            ? CaseReturn::query()
                ->where('condition', '!=', 'pendiente_inspeccion')
                ->whereDoesntHave('documents', fn (Builder $query) => $query
                    ->whereIn('document_type', ['inspeccion', 'evidencia_devolucion'])
                    ->whereIn('status', $documentableStatuses))
                ->count()
            : 0;
        $observedDocuments = $canViewDocuments
            ? DocumentEvidence::query()->where('status', 'observado')->count()
            : 0;
        $pendingDocumentsToValidate = $canViewDocuments
            ? DocumentEvidence::query()->where('status', 'cargado')->count()
            : 0;

        return view('dashboard.ops', [
            'openCases' => SurgeryCase::query()->whereNotIn('status', $activeStatuses)->count(),
            'todayCases' => SurgeryCase::query()->whereDate('scheduled_at', today())->count(),
            'surgeriesThisMonth' => SurgeryCase::query()
                ->whereBetween('scheduled_at', [$monthStart, $monthEnd])
                ->count(),
            'monthlyConsumptionValue' => CaseMaterialUsed::query()
                ->where('used_qty', '>', 0)
                ->whereHas('case', fn ($query) => $query->whereBetween('scheduled_at', [$monthStart, $monthEnd]))
                ->sum('subtotal'),
            'pendingToInvoiceAmount' => BillingRecord::query()
                ->whereIn('invoice_status', ['valorizado', 'pendiente_oc', 'pendiente_factura'])
                ->whereColumn('amount_paid', '<', 'amount')
                ->whereHas('case', fn ($query) => $query->whereBetween('scheduled_at', [$monthStart, $monthEnd]))
                ->sum(DB::raw('amount - amount_paid')),
            'upcoming48Cases' => SurgeryCase::query()
                ->whereNotIn('status', $activeStatuses)
                ->whereBetween('scheduled_at', [$now, $next48Hours])
                ->count(),
            'pendingClosureCases' => SurgeryCase::query()
                ->where('status', CaseStatus::PendienteCierre->value)
                ->count(),
            'closedPendingValuationCases' => BillingRecord::query()
                ->when(! $canViewBilling, fn ($query) => $query->whereRaw('1 = 0'))
                ->where('invoice_status', 'pendiente_valorizacion')
                ->count(),
            'consumptionDifferenceCases' => CaseMaterialUsed::query()
                ->where('difference_qty', '>', 0)
                ->distinct()
                ->count('case_id'),
            'zeroCostPendingCases' => BillingRecord::query()
                ->when(! $canViewBilling, fn ($query) => $query->whereRaw('1 = 0'))
                ->where('invoice_status', 'costo_cero_pendiente_aprobacion')
                ->count(),
            'returnsPendingInspection' => $canViewReturns
                ? CaseReturn::query()->where('condition', 'pendiente_inspeccion')->count()
                : 0,
            'returnsQuarantine' => $canViewReturns
                ? CaseReturn::query()->where('condition', 'cuarentena')->count()
                : 0,
            'returnsReleasedToday' => $canViewReturns
                ? CaseReturn::query()->where('condition', 'liberado')->whereDate('inspected_at', today())->count()
                : 0,
            'returnsWithFailure' => $canViewReturns
                ? CaseReturn::query()->where(function ($query): void {
                    $query->where('condition', 'bloqueado')
                        ->orWhere('inspection_result', 'falla_detectada');
                })->count()
                : 0,
            'returnsNonReusable' => $canViewReturns
                ? CaseReturn::query()->where(function ($query): void {
                    $query->whereIn('condition', ['desvalorizado', 'dado_de_baja'])
                        ->orWhereIn('inspection_result', ['no_reutilizable', 'baja']);
                })->count()
                : 0,
            'reportedFailures' => Failure::query()
                ->whereIn('status', $openFailureStatuses)
                ->count(),
            'openFailures' => Failure::query()->whereIn('status', $openFailureStatuses)->count(),
            'criticalFailures' => Failure::query()->whereIn('status', $openFailureStatuses)->whereIn('severity', ['alta', 'critica'])->count(),
            'failureBlockedLots' => InventoryLot::query()->where('status', 'falla_preventiva')->count(),
            'failuresPendingTechnicalReview' => Failure::query()->whereIn('status', ['reportada', 'bloqueada', 'en_revision'])->count(),
            'failuresPendingSupplier' => Failure::query()->whereIn('status', $openFailureStatuses)->where('requires_supplier', true)->count(),
            'failureTypeSummary' => $failureTypeSummary,
            'failureProductSummary' => $failureProductSummary,
            'closedWithoutReconciliationCases' => SurgeryCase::query()
                ->whereIn('status', [CaseStatus::Cerrado->value, CaseStatus::Cerrada->value])
                ->whereDoesntHave('reconciliation', fn ($query) => $query->where('status', 'conciliado'))
                ->count(),
            'valuedWithoutPurchaseOrderCases' => BillingRecord::query()
                ->where('invoice_status', 'valorizado')
                ->where(function ($query): void {
                    $query->whereNull('purchase_order')->orWhere('purchase_order', '');
                })
                ->count(),
            'pendingInvoiceCases' => BillingRecord::query()
                ->where('invoice_status', 'pendiente_factura')
                ->count(),
            'overdueDebtCases' => BillingRecord::query()
                ->whereNotIn('invoice_status', ['no_facturable', 'costo_cero_pendiente_aprobacion', 'costo_cero_aprobado', 'pendiente_valorizacion'])
                ->whereColumn('amount_paid', '<', 'amount')
                ->whereDate('due_date', '<', today())
                ->count(),
            'pendingDebtAmount' => $this->billingAmount(false),
            'overdueDebtAmount' => $this->billingAmount(true),
            'pendingValuedAmount' => BillingRecord::query()
                ->whereIn('invoice_status', ['valorizado', 'pendiente_oc', 'pendiente_factura'])
                ->whereColumn('amount_paid', '<', 'amount')
                ->sum(DB::raw('amount - amount_paid')),
            'agendaCases' => $agendaCases,
            'preoperativePendingCases' => $preoperativePendingCases,
            'activeReservations' => $stockSummary['active_reservations'],
            'redRisks' => $coverageSummary['red'],
            'yellowRisks' => $coverageSummary['yellow'],
            'riskSummaries' => $coverageSummary['rows'],
            'coverageSummary' => $coverageSummary,
            'incompleteReservationCases' => $stockSummary['incomplete_cases'],
            'inventoryAlerts' => $inventoryAlerts,
            'forecastSummary' => $forecastSummary,
            'canViewBilling' => $canViewBilling,
            'canViewInventory' => $canViewInventory,
            'canViewFailures' => $canViewFailures,
            'canViewCommercial' => $canViewCommercial,
            'canApprovals' => $canApprovals,
            'canViewReturns' => $canViewReturns,
            'canViewDocuments' => $canViewDocuments,
            'canViewSchedule' => $canViewSchedule,
            'canViewAlerts' => $canViewAlerts,
            'alertSummary' => $alertSummary,
            'canPrepareCases' => $canPrepareCases,
            'scheduleSummary' => $scheduleSummary,
            'currentUserRole' => $user->getRoleNames()->first(),
            'currentDateTime' => $now,
            'topInstitutions' => $topInstitutions,
            'topDoctors' => $topDoctors,
            'casesWithoutConsumptionEvidence' => $casesWithoutConsumptionEvidence,
            'casesWithoutSignedDocument' => $casesWithoutSignedDocument,
            'invoicesWithoutPurchaseOrder' => $invoicesWithoutPurchaseOrder,
            'failuresWithoutEvidence' => $failuresWithoutEvidence,
            'returnsWithoutInspectionEvidence' => $returnsWithoutInspectionEvidence,
            'observedDocuments' => $observedDocuments,
            'pendingDocumentsToValidate' => $pendingDocumentsToValidate,
        ]);
    }

    private function billingAmount(bool $overdue): float
    {
        $query = BillingRecord::query()
            ->whereNotIn('invoice_status', ['no_facturable', 'costo_cero_pendiente_aprobacion', 'costo_cero_aprobado', 'pendiente_valorizacion'])
            ->whereColumn('amount_paid', '<', 'amount');

        if ($overdue) {
            $query->whereDate('due_date', '<', today());
        }

        return round((float) $query->sum(DB::raw('amount - amount_paid')), 2);
    }
}
