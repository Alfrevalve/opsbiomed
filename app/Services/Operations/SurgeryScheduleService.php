<?php

namespace App\Services\Operations;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\CaseResourceAssignment;
use App\Models\InventoryLot;
use App\Models\SurgeryCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockRiskService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SurgeryScheduleService
{
    public const RESOURCE_TYPES = [
        'equipo',
        'motor',
        'consola',
        'pedal',
        'acople',
        'set',
    ];

    private const CLOSED_CASE_STATUSES = [
        CaseStatus::Cerrado,
        CaseStatus::Cerrada,
        CaseStatus::Facturacion,
        CaseStatus::Facturada,
        CaseStatus::Cancelado,
    ];

    public function __construct(
        private readonly StockRiskService $stockRiskService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function resourceTypeLabels(): array
    {
        return [
            'equipo' => 'Equipo',
            'motor' => 'Motor',
            'consola' => 'Consola',
            'pedal' => 'Pedal',
            'acople' => 'Acople',
            'set' => 'Set',
        ];
    }

    /**
     * @return array{
     *     cases: Collection<int, SurgeryCase>,
     *     contexts: Collection<int, array<string, mixed>>,
     *     conflicts: Collection<int, array<string, mixed>>,
     *     summary: array{total_cases: int, critical_conflicts: int, high_conflicts: int, missing_instrumentist: int, incomplete_reservations: int}
     * }
     */
    public function forRange(CarbonInterface $from, CarbonInterface $until): array
    {
        $cases = $this->scheduledCases($from, $until);
        $contexts = $cases
            ->mapWithKeys(fn (SurgeryCase $case): array => [$case->id => $this->caseContext($case)]);
        $conflicts = $this->detectConflicts($cases, $contexts);

        return [
            'cases' => $cases,
            'contexts' => $contexts,
            'conflicts' => $conflicts,
            'summary' => [
                'total_cases' => $cases->count(),
                'critical_conflicts' => $conflicts->where('severity', 'critico')->count(),
                'high_conflicts' => $conflicts->where('severity', 'alto')->count(),
                'missing_instrumentist' => $contexts
                    ->filter(fn (array $context): bool => $context['missing_instrumentist'])
                    ->count(),
                'incomplete_reservations' => $contexts
                    ->filter(fn (array $context): bool => $context['reservation_complete'] === false)
                    ->count(),
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function conflictsForCase(SurgeryCase $surgeryCase): Collection
    {
        if ($surgeryCase->scheduled_at === null) {
            return collect();
        }

        $from = $surgeryCase->scheduled_at->copy()->startOfDay();
        $schedule = $this->forRange($from, $from->copy()->endOfDay());

        return $schedule['conflicts']
            ->where('case_id', $surgeryCase->id)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function caseContext(SurgeryCase $surgeryCase): array
    {
        $surgeryCase->loadMissing([
            'institution',
            'patient',
            'surgeryType.kitRules',
            'reservations.inventoryLot.product',
            'assignedInstrumentist',
            'resourceAssignments.inventoryLot.product',
            'resourceAssignments.inventoryLot.warehouse',
        ]);

        $requiredRules = $surgeryCase->surgeryType?->kitRules
            ?->where('required', true)
            ->values() ?? collect();
        $activeReservations = $surgeryCase->reservations
            ->where('status', 'active')
            ->values();
        $reservationComplete = $activeReservations->isNotEmpty();
        $reservationLabel = $reservationComplete ? 'Reservada sin reglas de kit' : 'Sin material reservado';
        $risk = 'gray';

        if ($requiredRules->isNotEmpty()) {
            $reservationOverview = $this->stockRiskService->caseOverview($surgeryCase);
            $reservationComplete = $reservationOverview['complete'];
            $reservationLabel = $reservationComplete ? 'Completa' : 'Incompleta';
            $risks = $reservationOverview['risks'];

            $risk = $risks->contains(fn (array $row): bool => $row['risk'] === 'red')
                ? 'red'
                : ($risks->contains(fn (array $row): bool => $row['risk'] === 'yellow') ? 'yellow' : 'green');
        }

        return [
            'patient_initials' => $this->patientInitials($surgeryCase->patient?->full_name),
            'reservation_complete' => $reservationComplete,
            'reservation_label' => $reservationLabel,
            'risk' => $risk,
            'risk_label' => match ($risk) {
                'red' => 'Riesgo rojo',
                'yellow' => 'Riesgo amarillo',
                'green' => 'Controlado',
                default => 'No aplica',
            },
            'has_stock_critical' => $risk === 'red',
            'missing_instrumentist' => $surgeryCase->assigned_instrumentist_id === null,
            'resource_count' => $surgeryCase->resourceAssignments->count(),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function availableInstrumentists(): Collection
    {
        return User::query()
            ->role('Instrumentista')
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, InventoryLot>
     */
    public function availableResources(): Collection
    {
        return InventoryLot::query()
            ->with(['product', 'warehouse'])
            ->where('status', InventoryStatus::Apto)
            ->where('quantity', '>', 0)
            ->whereHas('product', fn (Builder $query) => $query
                ->where('classification', 'reusable')
                ->where('active', true))
            ->whereHas('warehouse', fn (Builder $query) => $query
                ->where('active', true)
                ->where('counts_as_immediate', true))
            ->orderBy('product_id')
            ->orderBy('lot')
            ->get();
    }

    public function assignInstrumentist(
        SurgeryCase $surgeryCase,
        ?int $instrumentistId,
        User $user,
    ): SurgeryCase {
        return DB::transaction(function () use ($surgeryCase, $instrumentistId, $user): SurgeryCase {
            $case = SurgeryCase::query()
                ->lockForUpdate()
                ->findOrFail($surgeryCase->id);
            $instrumentist = null;

            if ($instrumentistId !== null) {
                $instrumentist = User::query()
                    ->lockForUpdate()
                    ->find($instrumentistId);

                if ($instrumentist === null || ! $instrumentist->active || ! $instrumentist->hasRole('Instrumentista')) {
                    throw new DomainException('El usuario seleccionado no es un instrumentista activo.');
                }

                $scheduledMinute = $case->scheduled_at->copy()->startOfMinute();
                $hasConflict = SurgeryCase::query()
                    ->whereKeyNot($case->id)
                    ->where('assigned_instrumentist_id', $instrumentist->id)
                    ->whereNotIn('status', array_map(fn (CaseStatus $status): string => $status->value, self::CLOSED_CASE_STATUSES))
                    ->whereBetween('scheduled_at', [$scheduledMinute, $scheduledMinute->copy()->endOfMinute()])
                    ->exists();

                if ($hasConflict) {
                    throw new DomainException('El instrumentista ya esta asignado a otra cirugia en ese horario.');
                }
            }

            $before = $case->only([
                'assigned_instrumentist_id',
                'assigned_by',
                'assigned_at',
            ]);

            $case->update([
                'assigned_instrumentist_id' => $instrumentist?->id,
                'assigned_by' => $user->id,
                'assigned_at' => now(),
            ]);

            $this->auditLogger->record(
                $instrumentist === null
                    ? 'schedule.instrumentist_unassigned'
                    : 'schedule.instrumentist_assigned',
                $case,
                $before,
                [
                    'assigned_instrumentist_id' => $instrumentist?->id,
                    'instrumentist_name' => $instrumentist?->name,
                    'assigned_by' => $user->id,
                    'assigned_at' => $case->assigned_at?->toDateTimeString(),
                ],
            );

            return $case->refresh();
        });
    }

    /**
     * @param  array{resource_type: string, inventory_lot_id?: ?int}  $data
     */
    public function assignResource(
        SurgeryCase $surgeryCase,
        array $data,
        User $user,
    ): CaseResourceAssignment {
        return DB::transaction(function () use ($surgeryCase, $data, $user): CaseResourceAssignment {
            $case = SurgeryCase::query()
                ->lockForUpdate()
                ->findOrFail($surgeryCase->id);
            $inventoryLotId = $data['inventory_lot_id'] ?? null;
            $lot = null;

            if ($inventoryLotId !== null) {
                $lot = InventoryLot::query()
                    ->with(['product', 'warehouse'])
                    ->lockForUpdate()
                    ->findOrFail($inventoryLotId);

                if (! $this->canAssignResourceLot($lot)) {
                    throw new DomainException('El recurso seleccionado no esta disponible para asignacion inmediata.');
                }

                $scheduledMinute = $case->scheduled_at->copy()->startOfMinute();
                $hasConflict = CaseResourceAssignment::query()
                    ->where('inventory_lot_id', $lot->id)
                    ->whereHas('surgeryCase', fn (Builder $query): Builder => $query
                        ->whereKeyNot($case->id)
                        ->whereNotIn('status', array_map(fn (CaseStatus $status): string => $status->value, self::CLOSED_CASE_STATUSES))
                        ->whereBetween('scheduled_at', [$scheduledMinute, $scheduledMinute->copy()->endOfMinute()]))
                    ->exists();

                if ($hasConflict) {
                    throw new DomainException('El recurso ya esta asignado a otra cirugia en ese horario.');
                }

                $alreadyAssigned = CaseResourceAssignment::query()
                    ->where('surgery_case_id', $case->id)
                    ->where('inventory_lot_id', $lot->id)
                    ->exists();

                if ($alreadyAssigned) {
                    throw new DomainException('Este recurso ya se encuentra asignado al caso.');
                }
            }

            $assignment = CaseResourceAssignment::create([
                'surgery_case_id' => $case->id,
                'inventory_lot_id' => $lot?->id,
                'resource_type' => $data['resource_type'],
                'assigned_by' => $user->id,
                'assigned_at' => now(),
            ]);

            $this->auditLogger->record('schedule.resource_assigned', $assignment, [], [
                'case_id' => $case->id,
                'inventory_lot_id' => $lot?->id,
                'resource_type' => $assignment->resource_type,
                'assigned_by' => $user->id,
                'assigned_at' => $assignment->assigned_at?->toDateTimeString(),
            ]);

            return $assignment->load(['inventoryLot.product', 'inventoryLot.warehouse', 'assignedBy']);
        });
    }

    public function unassignResource(
        SurgeryCase $surgeryCase,
        CaseResourceAssignment $resourceAssignment,
        User $user,
    ): void {
        DB::transaction(function () use ($surgeryCase, $resourceAssignment, $user): void {
            $assignment = CaseResourceAssignment::query()
                ->lockForUpdate()
                ->findOrFail($resourceAssignment->id);

            if ($assignment->surgery_case_id !== $surgeryCase->id) {
                throw new DomainException('El recurso no corresponde al caso seleccionado.');
            }

            $before = $assignment->only([
                'surgery_case_id',
                'inventory_lot_id',
                'resource_type',
                'assigned_by',
                'assigned_at',
            ]);
            $assignment->delete();

            $this->auditLogger->record('schedule.resource_unassigned', $assignment, $before, [
                'case_id' => $surgeryCase->id,
                'unassigned_by' => $user->id,
            ]);
        });
    }

    /**
     * @return Collection<int, SurgeryCase>
     */
    private function scheduledCases(CarbonInterface $from, CarbonInterface $until): Collection
    {
        return SurgeryCase::query()
            ->with([
                'institution:id,name,debt_status',
                'doctor:id,name',
                'patient:id,full_name',
                'surgeryType.kitRules',
                'reservations.inventoryLot.product',
                'assignedInstrumentist:id,name',
                'resourceAssignments.inventoryLot.product',
                'resourceAssignments.inventoryLot.warehouse',
                'resourceAssignments.assignedBy:id,name',
            ])
            ->whereNotIn('status', array_map(
                fn (CaseStatus $status): string => $status->value,
                self::CLOSED_CASE_STATUSES,
            ))
            ->whereBetween('scheduled_at', [$from, $until])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, SurgeryCase>  $cases
     * @param  Collection<int, array<string, mixed>>  $contexts
     * @return Collection<int, array<string, mixed>>
     */
    private function detectConflicts(Collection $cases, Collection $contexts): Collection
    {
        $conflicts = collect();
        $this->detectInstrumentistConflicts($cases, $conflicts);
        $this->detectResourceConflicts($cases, $conflicts);
        $this->detectIncompleteSimultaneousCases($cases, $contexts, $conflicts);

        foreach ($cases as $surgeryCase) {
            $context = $contexts->get($surgeryCase->id);

            if ($context === null || $surgeryCase->scheduled_at === null) {
                continue;
            }

            $minutesUntil = now()->diffInMinutes($surgeryCase->scheduled_at, false);

            if ($minutesUntil < 0 || $minutesUntil > 2880) {
                continue;
            }

            $period = $minutesUntil <= 1440 ? '24 horas' : '48 horas';
            $severity = $minutesUntil <= 1440 ? 'critico' : 'alto';

            if ($context['missing_instrumentist']) {
                $conflicts->push($this->conflict(
                    $surgeryCase,
                    'sin_instrumentista',
                    'Instrumentista',
                    $severity,
                    "Asignar instrumentista antes de las proximas {$period}.",
                ));
            }

            if ($context['reservation_complete'] === false) {
                $conflicts->push($this->conflict(
                    $surgeryCase,
                    'reserva_incompleta',
                    'Material quirurgico',
                    $severity,
                    "Completar la reserva de material antes de las proximas {$period}.",
                ));
            }

            if ($context['has_stock_critical']) {
                $conflicts->push($this->conflict(
                    $surgeryCase,
                    'stock_critico',
                    'Cobertura MR8',
                    'alto',
                    'Revisar cobertura y forecast antes de confirmar la atencion.',
                ));
            }

            if ($surgeryCase->institution?->debt_status === 'vencida') {
                $conflicts->push($this->conflict(
                    $surgeryCase,
                    'deuda_vencida',
                    $surgeryCase->institution->name,
                    'bajo',
                    'Validar condicion comercial y cobranza antes de atender.',
                ));
            }
        }

        $severityOrder = [
            'critico' => 0,
            'alto' => 1,
            'medio' => 2,
            'bajo' => 3,
        ];

        return $conflicts
            ->sortBy(fn (array $conflict): int => $severityOrder[$conflict['severity']] ?? 99)
            ->values();
    }

    /**
     * @param  Collection<int, SurgeryCase>  $cases
     * @param  Collection<int, array<string, mixed>>  $conflicts
     */
    private function detectInstrumentistConflicts(Collection $cases, Collection $conflicts): void
    {
        $groups = $cases
            ->filter(fn (SurgeryCase $case): bool => $case->scheduled_at !== null && $case->assigned_instrumentist_id !== null)
            ->groupBy(fn (SurgeryCase $case): string => $case->scheduled_at->format('Y-m-d H:i').'|'.$case->assigned_instrumentist_id);

        foreach ($groups as $group) {
            $affectedCases = $group->unique('id')->values();

            if ($affectedCases->count() < 2) {
                continue;
            }

            $instrumentist = $affectedCases->first()?->assignedInstrumentist?->name ?? 'Instrumentista asignado';
            $relatedCodes = $affectedCases->pluck('case_code')->all();

            foreach ($affectedCases as $surgeryCase) {
                $conflicts->push($this->conflict(
                    $surgeryCase,
                    'cruce_instrumentista',
                    $instrumentist,
                    'critico',
                    'Reasignar instrumentista o reprogramar uno de los casos simultaneos.',
                    $relatedCodes,
                ));
            }
        }
    }

    /**
     * @param  Collection<int, SurgeryCase>  $cases
     * @param  Collection<int, array<string, mixed>>  $conflicts
     */
    private function detectResourceConflicts(Collection $cases, Collection $conflicts): void
    {
        $assignments = $cases->flatMap(function (SurgeryCase $case): Collection {
            return $case->resourceAssignments
                ->filter(fn (CaseResourceAssignment $assignment): bool => $assignment->inventory_lot_id !== null)
                ->map(fn (CaseResourceAssignment $assignment): array => [
                    'case' => $case,
                    'assignment' => $assignment,
                ]);
        });
        $groups = $assignments->groupBy(
            fn (array $item): string => $item['case']->scheduled_at->format('Y-m-d H:i').'|'.$item['assignment']->inventory_lot_id,
        );

        foreach ($groups as $group) {
            $affectedCases = $group->pluck('case')->unique('id')->values();

            if ($affectedCases->count() < 2) {
                continue;
            }

            /** @var CaseResourceAssignment $assignment */
            $assignment = $group->first()['assignment'];
            $resource = $this->resourceLabel($assignment);
            $relatedCodes = $affectedCases->pluck('case_code')->all();

            foreach ($affectedCases as $surgeryCase) {
                $conflicts->push($this->conflict(
                    $surgeryCase,
                    'cruce_recurso',
                    $resource,
                    'critico',
                    'Reasignar el recurso reutilizable o reprogramar uno de los casos simultaneos.',
                    $relatedCodes,
                ));
            }
        }
    }

    /**
     * @param  Collection<int, SurgeryCase>  $cases
     * @param  Collection<int, array<string, mixed>>  $contexts
     * @param  Collection<int, array<string, mixed>>  $conflicts
     */
    private function detectIncompleteSimultaneousCases(
        Collection $cases,
        Collection $contexts,
        Collection $conflicts,
    ): void {
        $groups = $cases
            ->filter(fn (SurgeryCase $case): bool => $case->scheduled_at !== null)
            ->groupBy(fn (SurgeryCase $case): string => $case->scheduled_at->format('Y-m-d H:i'));

        foreach ($groups as $group) {
            $affectedCases = $group->unique('id')->values();

            if ($affectedCases->count() < 2) {
                continue;
            }

            $relatedCodes = $affectedCases->pluck('case_code')->all();

            foreach ($affectedCases as $surgeryCase) {
                $context = $contexts->get($surgeryCase->id);

                if ($context === null || $context['reservation_complete'] !== false) {
                    continue;
                }

                $conflicts->push($this->conflict(
                    $surgeryCase,
                    'simultanea_sin_reserva',
                    'Material quirurgico',
                    'alto',
                    'Completar reserva o reprogramar antes de atender cirugias simultaneas.',
                    $relatedCodes,
                ));
            }
        }
    }

    /**
     * @param  list<string>  $relatedCases
     * @return array<string, mixed>
     */
    private function conflict(
        SurgeryCase $surgeryCase,
        string $type,
        string $resource,
        string $severity,
        string $suggestion,
        array $relatedCases = [],
    ): array {
        return [
            'case_id' => $surgeryCase->id,
            'case' => $surgeryCase,
            'type' => $type,
            'resource' => $resource,
            'severity' => $severity,
            'suggestion' => $suggestion,
            'related_cases' => $relatedCases,
        ];
    }

    private function canAssignResourceLot(InventoryLot $lot): bool
    {
        return $lot->status === InventoryStatus::Apto
            && (int) $lot->quantity > 0
            && $lot->product?->classification === 'reusable'
            && $lot->product?->active === true
            && $lot->warehouse?->active === true
            && $lot->warehouse?->counts_as_immediate === true;
    }

    private function resourceLabel(CaseResourceAssignment $assignment): string
    {
        $label = self::resourceTypeLabels()[$assignment->resource_type] ?? Str::headline($assignment->resource_type);
        $product = $assignment->inventoryLot?->product;

        if ($product === null) {
            return $label;
        }

        $tracking = collect([
            $product->product_code,
            $assignment->inventoryLot?->serial ?: $assignment->inventoryLot?->lot,
        ])->filter()->implode(' / ');

        return $tracking === '' ? $label : "{$label}: {$tracking}";
    }

    private function patientInitials(?string $name): string
    {
        if (blank($name)) {
            return '--';
        }

        return collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
