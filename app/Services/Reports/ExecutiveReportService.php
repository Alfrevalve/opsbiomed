<?php

namespace App\Services\Reports;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\BillingRecord;
use App\Models\CaseMaterialUsed;
use App\Models\Doctor;
use App\Models\Failure;
use App\Models\Institution;
use App\Models\InventoryImportIssue;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Services\Inventory\InventoryAlertService;
use App\Services\Inventory\ReplenishmentForecastService;
use App\Services\Inventory\SurgeryCoverageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ExecutiveReportService
{
    private const CLOSED_STATUSES = ['cerrado', 'cerrada', 'facturada'];

    private const NON_DEBT_INVOICE_STATUSES = [
        'pendiente_valorizacion',
        'no_facturable',
        'costo_cero_pendiente_aprobacion',
        'costo_cero_aprobado',
    ];

    private const INVOICE_STATUSES = [
        'pendiente_valorizacion',
        'valorizado',
        'pendiente_oc',
        'pendiente_factura',
        'facturado',
        'no_facturable',
        'costo_cero_pendiente_aprobacion',
        'costo_cero_aprobado',
    ];

    public function __construct(
        private readonly SurgeryCoverageService $coverageService,
        private readonly ReplenishmentForecastService $forecastService,
        private readonly InventoryAlertService $inventoryAlertService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        $normalized = array_filter(
            $filters,
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
        $dateFrom = isset($normalized['date_from'])
            ? Carbon::parse((string) $normalized['date_from'])->toDateString()
            : today()->startOfMonth()->toDateString();
        $dateTo = isset($normalized['date_to'])
            ? Carbon::parse((string) $normalized['date_to'])->toDateString()
            : today()->endOfMonth()->toDateString();

        return array_merge($normalized, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'institution_id' => isset($normalized['institution_id']) ? (int) $normalized['institution_id'] : null,
            'doctor_id' => isset($normalized['doctor_id']) ? (int) $normalized['doctor_id'] : null,
            'surgery_type_id' => isset($normalized['surgery_type_id']) ? (int) $normalized['surgery_type_id'] : null,
            'product_id' => isset($normalized['product_id']) ? (int) $normalized['product_id'] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'institutions' => Institution::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'doctors' => Doctor::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'surgery_types' => SurgeryType::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('active', true)->orderBy('product_code')->get(['id', 'product_code', 'name']),
            'case_statuses' => collect(CaseStatus::cases())->mapWithKeys(
                fn (CaseStatus $status): array => [$status->value => $status->label()],
            ),
            'invoice_statuses' => collect(self::INVOICE_STATUSES)->mapWithKeys(
                fn (string $status): array => [$status => $status],
            ),
            'urgencies' => [
                'critical' => 'Critica',
                'high' => 'Alta',
                'normal' => 'Normal',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function operations(User $user, array $filters): array
    {
        $cases = $this->caseQuery($filters, $user)
            ->with([
                'institution:id,name',
                'doctor:id,name',
                'surgeryType:id,name',
                'createdBy:id,name',
                'failures:id,case_id,status,description',
                'materialsUsed:id,case_id,difference_qty',
            ])
            ->orderBy('scheduled_at')
            ->get();
        $closedCases = $cases->filter(fn (SurgeryCase $case): bool => $this->isClosed($case));
        $closureAudits = AuditLog::query()
            ->where('action', 'case.closed')
            ->where('auditable_type', SurgeryCase::class)
            ->whereIn('auditable_id', $closedCases->modelKeys())
            ->orderBy('created_at')
            ->get(['auditable_id', 'created_at'])
            ->groupBy('auditable_id')
            ->map(fn (Collection $logs): mixed => $logs->first()?->created_at);
        $closureMinutes = $closedCases
            ->filter(fn (SurgeryCase $case): bool => $case->created_at && $case->updated_at)
            ->map(function (SurgeryCase $case) use ($closureAudits): int {
                $closedAt = $closureAudits->get($case->id) ?? $case->updated_at;

                return (int) $case->created_at->diffInMinutes($closedAt);
            });
        $incidentCases = $cases->filter(fn (SurgeryCase $case): bool => $case->failures->isNotEmpty());
        $differenceCases = $cases->filter(
            fn (SurgeryCase $case): bool => $case->materialsUsed->contains(fn ($material): bool => $material->difference_qty > 0),
        );

        return [
            'metrics' => [
                'total_cases' => $cases->count(),
                'average_closure_hours' => $closureMinutes->isNotEmpty()
                    ? round($closureMinutes->average() / 60, 1)
                    : 0,
                'cancelled_cases' => $cases->filter(fn (SurgeryCase $case): bool => $this->statusValue($case) === CaseStatus::Cancelado->value)->count(),
                'pending_cases' => $cases->filter(fn (SurgeryCase $case): bool => ! $this->isClosed($case) && $this->statusValue($case) !== CaseStatus::Cancelado->value)->count(),
                'incident_cases' => $incidentCases->count(),
                'difference_cases' => $differenceCases->count(),
            ],
            'by_date' => $this->groupCases($cases, fn (SurgeryCase $case): string => $case->scheduled_at?->format('d/m/Y') ?? 'Sin fecha'),
            'by_institution' => $this->groupCases($cases, fn (SurgeryCase $case): string => $case->institution?->name ?? 'Sin institucion'),
            'by_doctor' => $this->groupCases($cases, fn (SurgeryCase $case): string => $case->doctor?->name ?? 'Sin medico'),
            'by_status' => $cases
                ->groupBy(fn (SurgeryCase $case): string => $this->statusValue($case))
                ->map(fn (Collection $group, string $status): array => [
                    'label' => $this->statusLabel($status),
                    'cases' => $group->count(),
                ])
                ->values(),
            'by_responsible' => $this->groupCases($cases, fn (SurgeryCase $case): string => $case->createdBy?->name ?? 'Sin responsable'),
            'incident_cases_list' => $incidentCases->take(50)->map(fn (SurgeryCase $case): array => $this->caseRow($case))->values(),
            'difference_cases_list' => $differenceCases->take(50)->map(fn (SurgeryCase $case): array => $this->caseRow($case))->values(),
            'failure_report' => $this->failureReport($user, $filters),
            'limited' => $user->hasRole('Instrumentista'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function commercial(User $user, array $filters): array
    {
        $materials = $this->consumptionQuery($filters, $user)->get();
        $previousFilters = $this->previousPeriodFilters($filters);
        $previousMaterials = $this->consumptionQuery($previousFilters, $user)->get();
        $byInstitution = $this->aggregateConsumption($materials, 'institution');
        $byDoctor = $this->aggregateConsumption($materials, 'doctor');
        $byProduct = $this->aggregateConsumption($materials, 'product');
        $previousByInstitution = $this->aggregateConsumption($previousMaterials, 'institution')->keyBy('id');
        $growth = $byInstitution->map(function (array $row) use ($previousByInstitution): array {
            $previousUnits = (int) ($previousByInstitution->get($row['id'])['units'] ?? 0);
            $row['previous_units'] = $previousUnits;
            $row['growth_percent'] = $previousUnits === 0
                ? ($row['units'] > 0 ? 100 : 0)
                : round((($row['units'] - $previousUnits) / $previousUnits) * 100, 1);

            return $row;
        })->sortByDesc('growth_percent')->values();
        $activeReservationInstitutionIds = Reservation::query()
            ->where('status', 'active')
            ->whereHas('case', fn (Builder $query): Builder => $this->applyCaseFilters($query, $filters, $user))
            ->with('case:id,institution_id')
            ->get()
            ->pluck('case.institution_id')
            ->filter()
            ->unique()
            ->values();
        $opportunities = $byInstitution
            ->filter(fn (array $row): bool => $row['cases'] >= 2 && ! $activeReservationInstitutionIds->contains($row['id']))
            ->map(fn (array $row): array => $row + ['reason' => 'Consumo recurrente sin reserva activa en el periodo.'])
            ->values();
        $recentInstitutionIds = $materials->pluck('case.institution_id')->filter()->unique();
        $noRecentConsumption = Institution::query()
            ->where('active', true)
            ->whereNotIn('id', $recentInstitutionIds->all() ?: [0])
            ->orderBy('name')
            ->get(['id', 'name']);
        $zeroCost = $materials->filter(fn (CaseMaterialUsed $material): bool => (bool) $material->cost_zero);

        return [
            'metrics' => [
                'consumption_units' => (int) $materials->sum('used_qty'),
                'consumption_value' => round((float) $materials->sum('subtotal'), 2),
                'institutions_with_consumption' => $byInstitution->count(),
                'key_doctors' => $byDoctor->take(5)->count(),
                'zero_cost_cases' => $zeroCost->pluck('case_id')->unique()->count(),
            ],
            'by_institution' => $byInstitution,
            'by_doctor' => $byDoctor,
            'by_product' => $byProduct->take(25)->values(),
            'growth' => $growth->take(25),
            'opportunities' => $opportunities->take(25),
            'no_recent_consumption' => $noRecentConsumption,
            'zero_cost_by_institution' => $this->aggregateConsumption($zeroCost, 'institution'),
            'zero_cost_by_doctor' => $this->aggregateConsumption($zeroCost, 'doctor'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function billing(User $user, array $filters): array
    {
        $records = $this->billingQuery($filters, $user)
            ->with(['case.institution:id,name', 'case.doctor:id,name', 'case.surgeryType:id,name'])
            ->latest('id')
            ->get();
        $debtRecords = $records->reject(
            fn (BillingRecord $record): bool => in_array($record->invoice_status, self::NON_DEBT_INVOICE_STATUSES, true),
        );
        $pendingRecords = $debtRecords->filter(fn (BillingRecord $record): bool => $record->balance > 0);
        $overdueRecords = $pendingRecords->filter(fn (BillingRecord $record): bool => $this->isOverdue($record));
        $approvals = Approval::query()
            ->where('type', 'cost_zero')
            ->whereHas('case', fn (Builder $query): Builder => $this->applyCaseFilters($query, $filters, $user))
            ->with('case.institution:id,name')
            ->get();

        return [
            'metrics' => [
                'valued_amount' => round((float) $records->sum('amount'), 2),
                'invoiced_amount' => round((float) $records->where('invoice_status', 'facturado')->sum('amount'), 2),
                'pending_amount' => round((float) $pendingRecords->sum(fn (BillingRecord $record): float => $record->balance), 2),
                'overdue_amount' => round((float) $overdueRecords->sum(fn (BillingRecord $record): float => $record->balance), 2),
                'pending_purchase_order' => $records->filter(fn (BillingRecord $record): bool => $record->invoice_status === 'pendiente_oc' || ($record->invoice_status === 'valorizado' && blank($record->purchase_order)))->count(),
                'pending_invoice' => $records->where('invoice_status', 'pendiente_factura')->count(),
                'zero_cost_pending' => $approvals->where('status', 'pendiente_aprobacion')->count(),
                'zero_cost_approved' => $approvals->where('status', 'aprobado')->count(),
                'zero_cost_rejected' => $approvals->where('status', 'rechazado')->count(),
            ],
            'by_institution' => $this->aggregateDebt($debtRecords),
            'aging' => $this->agingBuckets($overdueRecords),
            'records' => $records->take(100)->map(fn (BillingRecord $record): array => $this->billingRow($record))->values(),
            'zero_cost_approvals' => $approvals->map(fn (Approval $approval): array => [
                'case_code' => $approval->case?->case_code,
                'institution' => $approval->case?->institution?->name,
                'status' => $approval->status,
                'requested_at' => $approval->created_at?->format('d/m/Y'),
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function inventory(User $user, array $filters): array
    {
        $coverage = $this->coverageService->summary();
        $forecastFilters = array_filter([
            'surgery_type' => isset($filters['surgery_type_id'])
                ? SurgeryType::query()->whereKey($filters['surgery_type_id'])->value('code')
                : null,
            'urgency' => $filters['urgency'] ?? null,
            'length' => $filters['length'] ?? null,
            'diameter' => $filters['diameter'] ?? null,
            'product_type' => $filters['product_type'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
        $forecast = $this->forecastService->summary($forecastFilters);
        $coverageRows = $this->filterCoverageRows($coverage['rows'], $filters);
        $forecastRows = $this->filterForecastRows($forecast['rows'], $filters);
        $lots = InventoryLot::query()
            ->with(['product:id,product_code,name', 'warehouse:id,name,type'])
            ->withSum(['reservations as reserved_active' => fn (Builder $query): Builder => $query->where('status', 'active')], 'quantity')
            ->when($filters['product_id'] ?? null, fn (Builder $query, int $productId): Builder => $query->where('product_id', $productId))
            ->get();
        $expiredLots = $lots->filter(fn (InventoryLot $lot): bool => $lot->status === InventoryStatus::Vencido || ($lot->expiry !== null && $lot->expiry->isBefore(today())));
        $expiringLots = $lots->filter(fn (InventoryLot $lot): bool => $lot->expiry !== null && $lot->expiry->between(today(), today()->addDays(90)) && $lot->quantity > 0);
        $blockedLots = $lots->filter(fn (InventoryLot $lot): bool => $lot->status === InventoryStatus::Bloqueado);
        $quarantineLots = $lots->filter(fn (InventoryLot $lot): bool => $lot->status === InventoryStatus::Cuarentena);
        $issues = InventoryImportIssue::query()
            ->where('status', 'pending')
            ->whereHas('catalogImport', fn (Builder $query): Builder => $query->where('status', 'committed'))
            ->when($filters['product_id'] ?? null, fn (Builder $query, int $productId): Builder => $query->where('product_id', $productId))
            ->with(['product:id,product_code,name', 'warehouse:id,name'])
            ->latest('id')
            ->get();
        $alertSummary = $this->inventoryAlertService->summary();

        return [
            'metrics' => [
                'under_minimum' => $coverageRows->where('risk', 'red')->count(),
                'under_target' => $coverageRows->whereIn('risk', ['red', 'yellow'])->count(),
                'critical_combinations' => $coverageRows->where('criticality', 'critica')->where('risk', 'red')->count(),
                'expired' => $expiredLots->count(),
                'expiring_soon' => $expiringLots->count(),
                'blocked' => $blockedLots->count(),
                'quarantine' => $quarantineLots->count(),
                'pending_inconsistencies' => $issues->count(),
                'urgent_purchase' => $forecastRows->where('urgency', 'critical')->count(),
            ],
            'under_minimum' => $coverageRows->where('risk', 'red')->values(),
            'under_target' => $coverageRows->whereIn('risk', ['red', 'yellow'])->values(),
            'expired_lots' => $expiredLots->take(50)->values(),
            'expiring_lots' => $expiringLots->take(50)->values(),
            'blocked_lots' => $blockedLots->take(50)->values(),
            'quarantine_lots' => $quarantineLots->take(50)->values(),
            'issues' => $issues->take(50),
            'forecast' => $forecastRows->values(),
            'alert_summary' => $alertSummary,
            'coverage' => $coverage,
            'failure_report' => $this->failureReport($user, $filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function failureReport(User $user, array $filters): array
    {
        $failures = Failure::query()
            ->with([
                'product:id,product_code,name',
                'inventoryLot.product:id,product_code,name',
                'case.institution:id,name',
                'case.doctor:id,name',
                'case.surgeryType:id,name',
                'reportedBy:id,name',
                'releasedBy:id,name',
            ])
            ->whereBetween('created_at', [
                Carbon::parse((string) $filters['date_from'])->startOfDay(),
                Carbon::parse((string) $filters['date_to'])->endOfDay(),
            ])
            ->when($filters['institution_id'] ?? null, fn (Builder $query, int $id): Builder => $query->whereHas('case', fn (Builder $caseQuery): Builder => $caseQuery->where('institution_id', $id)))
            ->when($filters['doctor_id'] ?? null, fn (Builder $query, int $id): Builder => $query->whereHas('case', fn (Builder $caseQuery): Builder => $caseQuery->where('doctor_id', $id)))
            ->when($filters['surgery_type_id'] ?? null, fn (Builder $query, int $id): Builder => $query->whereHas('case', fn (Builder $caseQuery): Builder => $caseQuery->where('surgery_type_id', $id)))
            ->when($filters['case_status'] ?? null, fn (Builder $query, string $status): Builder => $query->whereHas('case', fn (Builder $caseQuery): Builder => $caseQuery->where('status', $status)))
            ->when($filters['product_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where(function (Builder $productQuery) use ($id): void {
                $productQuery->where('product_id', $id)->orWhereHas('inventoryLot', fn (Builder $lotQuery): Builder => $lotQuery->where('product_id', $id));
            }))
            ->when($user->hasRole('Instrumentista'), fn (Builder $query): Builder => $query->where(function (Builder $scopeQuery) use ($user): void {
                $scopeQuery->where('reported_by', $user->id)->orWhereHas('case', fn (Builder $caseQuery): Builder => $caseQuery->where('created_by', $user->id));
            }))
            ->latest('id')
            ->get();

        $byMonth = $failures
            ->groupBy(fn (Failure $failure): string => $failure->created_at?->format('Y-m') ?? 'sin_fecha')
            ->map(fn (Collection $group, string $month): array => [
                'label' => $month === 'sin_fecha' ? 'Sin fecha' : Carbon::createFromFormat('Y-m', $month)->format('m/Y'),
                'failures' => $group->count(),
            ])
            ->values();
        $byProduct = $failures
            ->groupBy(fn (Failure $failure): string => (string) ($failure->product_id ?? $failure->inventoryLot?->product_id ?? 'none'))
            ->map(function (Collection $group): array {
                $first = $group->first();
                $product = $first->product ?? $first->inventoryLot?->product;

                return [
                    'label' => trim(($product?->product_code ?? 'Sin codigo').' - '.($product?->name ?? 'Producto no especificado')),
                    'failures' => $group->count(),
                    'critical' => $group->whereIn('severity', ['alta', 'critica'])->count(),
                ];
            })
            ->sortByDesc('failures')
            ->values();
        $byInstitution = $failures
            ->groupBy(fn (Failure $failure): string => (string) ($failure->case?->institution_id ?? 'none'))
            ->map(fn (Collection $group): array => [
                'label' => $group->first()->case?->institution?->name ?? 'Sin institucion',
                'failures' => $group->count(),
            ])
            ->sortByDesc('failures')
            ->values();
        $releasedFailures = $failures->filter(fn (Failure $failure): bool => $failure->released_at !== null);

        return [
            'metrics' => [
                'total' => $failures->count(),
                'open' => $failures->whereIn('status', ['reportada', 'bloqueada', 'en_revision', 'pendiente_repuesto'])->count(),
                'critical' => $failures->whereIn('severity', ['alta', 'critica'])->count(),
                'pending_supplier' => $failures->where('requires_supplier', true)->whereNotIn('status', ['liberada', 'dada_de_baja', 'cerrada'])->count(),
                'average_release_hours' => $releasedFailures->isEmpty() ? 0 : round($releasedFailures->average(fn (Failure $failure): float => $failure->created_at->diffInMinutes($failure->released_at) / 60), 1),
            ],
            'by_month' => $byMonth,
            'by_product' => $byProduct->take(25)->values(),
            'by_institution' => $byInstitution->take(25)->values(),
            'repeated_products' => $byProduct->filter(fn (array $row): bool => $row['failures'] > 1)->take(25)->values(),
            'rows' => $failures->take(100)->map(fn (Failure $failure): array => [
                'id' => $failure->id,
                'product' => trim(($failure->product?->product_code ?? $failure->inventoryLot?->product?->product_code ?? 'Sin codigo').' - '.($failure->product?->name ?? $failure->inventoryLot?->product?->name ?? 'Producto no especificado')),
                'institution' => $failure->case?->institution?->name ?? 'Sin institucion',
                'severity' => $failure->severity,
                'status' => $failure->status,
                'created_at' => $failure->created_at?->format('d/m/Y H:i'),
                'released_at' => $failure->released_at?->format('d/m/Y H:i'),
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function caseQuery(array $filters, User $user): Builder
    {
        return $this->applyCaseFilters(SurgeryCase::query(), $filters, $user);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyCaseFilters(Builder $query, array $filters, User $user): Builder
    {
        return $query
            ->when($filters['date_from'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('scheduled_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('scheduled_at', '<=', $date))
            ->when($filters['institution_id'] ?? null, fn (Builder $builder, int $id): Builder => $builder->where('institution_id', $id))
            ->when($filters['doctor_id'] ?? null, fn (Builder $builder, int $id): Builder => $builder->where('doctor_id', $id))
            ->when($filters['surgery_type_id'] ?? null, fn (Builder $builder, int $id): Builder => $builder->where('surgery_type_id', $id))
            ->when($filters['case_status'] ?? null, fn (Builder $builder, string $status): Builder => $builder->where('status', $status))
            ->when($filters['product_id'] ?? null, fn (Builder $builder, int $id): Builder => $builder->whereHas('materialsUsed.inventoryLot', fn (Builder $lotQuery): Builder => $lotQuery->where('product_id', $id)))
            ->when($user->hasRole('Instrumentista'), fn (Builder $builder): Builder => $builder->where('created_by', $user->id));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function consumptionQuery(array $filters, User $user): Builder
    {
        return CaseMaterialUsed::query()
            ->where('used_qty', '>', 0)
            ->whereHas('case', fn (Builder $query): Builder => $this->applyCaseFilters($query, $filters, $user))
            ->when($filters['product_id'] ?? null, fn (Builder $query, int $id): Builder => $query->whereHas('inventoryLot', fn (Builder $lotQuery): Builder => $lotQuery->where('product_id', $id)))
            ->with([
                'case.institution:id,name',
                'case.doctor:id,name',
                'case.surgeryType:id,name',
                'inventoryLot.product:id,product_code,name',
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function billingQuery(array $filters, User $user): Builder
    {
        return BillingRecord::query()
            ->whereHas('case', fn (Builder $query): Builder => $this->applyCaseFilters($query, $filters, $user))
            ->when($filters['invoice_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('invoice_status', $status));
    }

    /**
     * @param  Collection<int, SurgeryCase>  $cases
     */
    private function groupCases(Collection $cases, callable $key): Collection
    {
        return $cases
            ->groupBy($key)
            ->map(fn (Collection $group, string $label): array => ['label' => $label, 'cases' => $group->count()])
            ->sortByDesc('cases')
            ->values();
    }

    /**
     * @param  Collection<int, CaseMaterialUsed>  $materials
     */
    private function aggregateConsumption(Collection $materials, string $dimension): Collection
    {
        return $materials
            ->groupBy(fn (CaseMaterialUsed $material): string => $this->consumptionKey($material, $dimension))
            ->map(function (Collection $group) use ($dimension): array {
                $first = $group->first();
                $name = $this->consumptionName($first, $dimension);
                $id = $this->consumptionId($first, $dimension);

                return [
                    'id' => $id,
                    'label' => $name,
                    'units' => (int) $group->sum('used_qty'),
                    'cases' => $group->pluck('case_id')->unique()->count(),
                    'value' => round((float) $group->sum('subtotal'), 2),
                ];
            })
            ->sortByDesc('units')
            ->values();
    }

    private function consumptionKey(CaseMaterialUsed $material, string $dimension): string
    {
        return $dimension.'|'.$this->consumptionId($material, $dimension);
    }

    private function consumptionId(CaseMaterialUsed $material, string $dimension): string
    {
        return match ($dimension) {
            'institution' => (string) ($material->case?->institution?->id ?? 'none'),
            'doctor' => (string) ($material->case?->doctor?->id ?? 'none'),
            'product' => (string) ($material->inventoryLot?->product?->id ?? 'none'),
            default => 'none',
        };
    }

    private function consumptionName(?CaseMaterialUsed $material, string $dimension): string
    {
        return match ($dimension) {
            'institution' => $material?->case?->institution?->name ?? 'Sin institucion',
            'doctor' => $material?->case?->doctor?->name ?? 'Sin medico',
            'product' => trim(($material?->inventoryLot?->product?->product_code ?? 'Sin codigo').' - '.($material?->inventoryLot?->product?->name ?? 'Sin producto')),
            default => 'Sin clasificacion',
        };
    }

    /**
     * @param  Collection<int, BillingRecord>  $records
     */
    private function aggregateDebt(Collection $records): Collection
    {
        return $records
            ->filter(fn (BillingRecord $record): bool => $record->balance > 0)
            ->groupBy(fn (BillingRecord $record): string => (string) ($record->case?->institution?->id ?? 'none'))
            ->map(function (Collection $group): array {
                $first = $group->first();
                $overdue = $group->filter(fn (BillingRecord $record): bool => $this->isOverdue($record));

                return [
                    'label' => $first->case?->institution?->name ?? 'Sin institucion',
                    'cases' => $group->pluck('case_id')->unique()->count(),
                    'pending' => round((float) $group->sum(fn (BillingRecord $record): float => $record->balance), 2),
                    'overdue' => round((float) $overdue->sum(fn (BillingRecord $record): float => $record->balance), 2),
                ];
            })
            ->sortByDesc('pending')
            ->values();
    }

    /**
     * @param  Collection<int, BillingRecord>  $records
     * @return array<string, array{cases: int, amount: float}>
     */
    private function agingBuckets(Collection $records): array
    {
        $buckets = [
            '0-30 dias' => ['cases' => 0, 'amount' => 0.0],
            '31-60 dias' => ['cases' => 0, 'amount' => 0.0],
            '61-90 dias' => ['cases' => 0, 'amount' => 0.0],
            'Mas de 90 dias' => ['cases' => 0, 'amount' => 0.0],
        ];

        foreach ($records as $record) {
            if (! $record->due_date) {
                continue;
            }
            $days = max(1, (int) $record->due_date->diffInDays(today()));
            $bucket = match (true) {
                $days <= 30 => '0-30 dias',
                $days <= 60 => '31-60 dias',
                $days <= 90 => '61-90 dias',
                default => 'Mas de 90 dias',
            };
            $buckets[$bucket]['cases']++;
            $buckets[$bucket]['amount'] = round($buckets[$bucket]['amount'] + $record->balance, 2);
        }

        return $buckets;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterCoverageRows(Collection $rows, array $filters): Collection
    {
        $product = isset($filters['product_id']) ? Product::find($filters['product_id']) : null;
        $productKey = $product ? $this->combinationKey($product->length_cm, $product->diameter_mm, $product->cut_type, $product->component_type) : null;
        $surgeryTypeCode = isset($filters['surgery_type_id'])
            ? SurgeryType::query()->whereKey($filters['surgery_type_id'])->value('code')
            : null;
        $risk = match ($filters['urgency'] ?? null) {
            'critical' => 'red',
            'high' => 'yellow',
            'normal' => 'green',
            default => null,
        };

        return $rows->filter(function (array $row) use ($productKey, $surgeryTypeCode, $risk, $filters): bool {
            return ($productKey === null || $row['key'] === $productKey)
                && ($surgeryTypeCode === null || $row['surgery_type_code'] === $surgeryTypeCode)
                && ($risk === null || $row['risk'] === $risk)
                && (($filters['length'] ?? null) === null || (float) $filters['length'] === (float) $row['length_cm'])
                && (($filters['diameter'] ?? null) === null || (float) $filters['diameter'] === (float) $row['diameter_mm']);
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterForecastRows(Collection $rows, array $filters): Collection
    {
        if (! isset($filters['product_id'])) {
            return $rows;
        }
        $productCode = Product::query()->whereKey($filters['product_id'])->value('product_code');

        return $rows->filter(fn (array $row): bool => $productCode && in_array($productCode, $row['product_codes'] ?? [], true));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function previousPeriodFilters(array $filters): array
    {
        $from = Carbon::parse((string) $filters['date_from']);
        $to = Carbon::parse((string) $filters['date_to']);
        $days = $from->diffInDays($to) + 1;

        return array_merge($filters, [
            'date_from' => $from->copy()->subDays($days)->toDateString(),
            'date_to' => $from->copy()->subDay()->toDateString(),
        ]);
    }

    private function isClosed(SurgeryCase $case): bool
    {
        return in_array($this->statusValue($case), self::CLOSED_STATUSES, true);
    }

    private function statusValue(SurgeryCase $case): string
    {
        return $case->status instanceof CaseStatus ? $case->status->value : (string) $case->status;
    }

    private function statusLabel(string $status): string
    {
        return CaseStatus::tryFrom($status)?->label() ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * @return array<string, mixed>
     */
    private function caseRow(SurgeryCase $case): array
    {
        return [
            'case_id' => $case->id,
            'case_code' => $case->case_code,
            'scheduled_at' => $case->scheduled_at?->format('d/m/Y H:i'),
            'institution' => $case->institution?->name ?? 'Sin institucion',
            'doctor' => $case->doctor?->name ?? 'Sin medico',
            'surgery_type' => $case->surgeryType?->name ?? 'Sin tipo',
            'status' => $this->statusLabel($this->statusValue($case)),
            'responsible' => $case->createdBy?->name ?? 'Sin responsable',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function billingRow(BillingRecord $record): array
    {
        return [
            'case_code' => $record->case?->case_code,
            'institution' => $record->case?->institution?->name ?? 'Sin institucion',
            'scheduled_at' => $record->case?->scheduled_at?->format('d/m/Y H:i'),
            'invoice_status' => $record->invoice_status,
            'amount' => round((float) $record->amount, 2),
            'amount_paid' => round((float) $record->amount_paid, 2),
            'balance' => $record->balance,
            'payment_status' => $record->payment_status,
            'due_date' => $record->due_date?->format('d/m/Y'),
        ];
    }

    private function isOverdue(BillingRecord $record): bool
    {
        return $record->balance > 0
            && $record->due_date?->isBefore(today()) === true
            && ! in_array($record->invoice_status, self::NON_DEBT_INVOICE_STATUSES, true);
    }

    private function combinationKey(mixed $length, mixed $diameter, ?string $cutType, ?string $componentType): string
    {
        return implode('|', [
            number_format((float) $length, 2, '.', ''),
            number_format((float) $diameter, 2, '.', ''),
            $cutType ?? '',
            $componentType ?? '',
        ]);
    }
}
