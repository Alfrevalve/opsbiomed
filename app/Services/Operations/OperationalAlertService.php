<?php

namespace App\Services\Operations;

use App\Enums\CaseStatus;
use App\Models\Approval;
use App\Models\BillingRecord;
use App\Models\CaseReturn;
use App\Models\DocumentEvidence;
use App\Models\Failure;
use App\Models\OperationalAlert;
use App\Models\OperationalSla;
use App\Models\SurgeryCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockRiskService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationalAlertService
{
    private const CLOSED_CASE_STATUSES = [
        CaseStatus::Cerrado->value,
        CaseStatus::Cerrada->value,
        CaseStatus::Facturacion->value,
        CaseStatus::Facturada->value,
        CaseStatus::Cancelado->value,
    ];

    public function __construct(
        private readonly OperationalSlaService $slaService,
        private readonly StockRiskService $stockRiskService,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationDispatchService $notificationService,
    ) {}

    /**
     * @return array{slas: int, created: int, expired: int, notifications: int}
     */
    public function evaluate(?CarbonInterface $now = null): array
    {
        $at = $now ? Carbon::parse($now) : now();
        $this->slaService->ensureDefaults();
        $created = 0;
        $notifications = 0;

        foreach (OperationalSla::query()->where('active', true)->orderBy('id')->get() as $sla) {
            foreach ($this->candidatesFor($sla, $at) as $candidate) {
                $existing = $this->activeAlertFor($sla, $candidate['alertable_type'], $candidate['alertable_id']);

                if ($existing !== null) {
                    continue;
                }

                $alert = $this->createAlert($sla, $candidate, $at);
                $created++;
                $notifications += $this->notificationService->dispatchInternal($alert) !== null ? 1 : 0;
            }
        }

        $expired = $this->expireOpenAlerts($at);

        return [
            'slas' => OperationalSla::query()->where('active', true)->count(),
            'created' => $created,
            'expired' => $expired,
            'notifications' => $notifications,
        ];
    }

    public function acknowledge(OperationalAlert $alert, User $user, ?string $note = null): OperationalAlert
    {
        return $this->changeStatus($alert, $user, 'acknowledged', 'alert.acknowledged', $note);
    }

    public function resolve(OperationalAlert $alert, User $user, ?string $note = null): OperationalAlert
    {
        return $this->changeStatus($alert, $user, 'resolved', 'alert.resolved', $note);
    }

    public function dismiss(OperationalAlert $alert, User $user, ?string $note = null): OperationalAlert
    {
        return $this->changeStatus($alert, $user, 'dismissed', 'alert.dismissed', $note);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function candidatesFor(OperationalSla $sla, CarbonInterface $now): Collection
    {
        return match ($sla->code) {
            'request_without_reservation' => $this->requestWithoutReservation($now),
            'surgery_without_coverage' => $this->surgeryWithoutCoverage($now),
            'return_pending_inspection' => $this->returnPendingInspection($now),
            'critical_failure_unreviewed' => $this->criticalFailureUnreviewed($now),
            'document_pending_validation' => $this->documentPendingValidation($now),
            'case_closed_without_billing' => $this->caseClosedWithoutBilling($now),
            'overdue_collection' => $this->overdueCollection($now),
            'zero_cost_pending_approval' => $this->zeroCostPendingApproval($now),
            default => collect(),
        };
    }

    /** @return Collection<int, array<string, mixed>> */
    private function requestWithoutReservation(CarbonInterface $now): Collection
    {
        return SurgeryCase::query()
            ->whereNotIn('status', self::CLOSED_CASE_STATUSES)
            ->whereDoesntHave('reservations', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->map(fn (SurgeryCase $case): array => $this->candidate(
                $case,
                $case->created_at ?? $now,
                $this->targetDue($case->created_at ?? $now, 1440),
                'Solicitud sin reserva',
                "La solicitud {$case->case_code} no tiene una reserva activa de material.",
                ['case_code' => $case->case_code],
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function surgeryWithoutCoverage(CarbonInterface $now): Collection
    {
        return $this->stockRiskService->incompleteReservationCases()
            ->map(fn (SurgeryCase $case): array => $this->candidate(
                $case,
                $case->created_at ?? $now,
                $case->scheduled_at?->copy() ?? $now,
                'Cirugía próxima sin cobertura',
                "La cirugía {$case->case_code} tiene combinaciones MR8 sin reserva completa.",
                [
                    'case_code' => $case->case_code,
                    'scheduled_at' => $case->scheduled_at?->toIso8601String(),
                ],
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function returnPendingInspection(CarbonInterface $now): Collection
    {
        return CaseReturn::query()
            ->with('case')
            ->where('condition', 'pendiente_inspeccion')
            ->get()
            ->map(fn (CaseReturn $return): array => $this->candidate(
                $return,
                $return->created_at ?? $now,
                $this->targetDue($return->created_at ?? $now, 1440),
                'Devolución pendiente de inspección',
                'La devolución requiere inspección antes de volver a disponibilidad.',
                [
                    'case_code' => $return->case?->case_code,
                    'trace_code' => $return->trace_code,
                ],
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function criticalFailureUnreviewed(CarbonInterface $now): Collection
    {
        return Failure::query()
            ->with('case')
            ->where('severity', 'critica')
            ->whereIn('status', ['reportada', 'bloqueada', 'en_revision', 'pendiente_repuesto', 'abierta'])
            ->whereNull('reviewed_at')
            ->get()
            ->map(fn (Failure $failure): array => $this->candidate(
                $failure,
                $failure->created_at ?? $now,
                $this->targetDue($failure->created_at ?? $now, 240),
                'Falla crítica sin revisión',
                'La falla crítica requiere revisión de Dirección Técnica.',
                [
                    'case_code' => $failure->case?->case_code,
                    'failure_type' => $failure->failure_type,
                ],
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function documentPendingValidation(CarbonInterface $now): Collection
    {
        return DocumentEvidence::query()
            ->whereIn('status', ['pendiente', 'cargado'])
            ->get()
            ->map(fn (DocumentEvidence $document): array => $this->candidate(
                $document,
                $document->created_at ?? $now,
                $this->targetDue($document->created_at ?? $now, 1440),
                'Documento pendiente de validación',
                'El documento cargado requiere validación operativa.',
                ['document_type' => $document->document_type],
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function caseClosedWithoutBilling(CarbonInterface $now): Collection
    {
        return SurgeryCase::query()
            ->with('billingRecord')
            ->whereIn('status', [CaseStatus::Cerrado->value, CaseStatus::Cerrada->value])
            ->where(function ($query): void {
                $query->whereDoesntHave('billingRecord')
                    ->orWhereHas('billingRecord', fn ($billing) => $billing->whereIn('invoice_status', [
                        'pendiente_valorizacion',
                        'valorizado',
                        'pendiente_oc',
                        'pendiente_factura',
                    ]));
            })
            ->get()
            ->map(fn (SurgeryCase $case): array => $this->candidate(
                $case,
                $case->updated_at ?? $case->created_at ?? $now,
                $this->targetDue($case->updated_at ?? $case->created_at ?? $now, 2880),
                'Caso cerrado sin facturación',
                "El caso {$case->case_code} está cerrado y aún no completa su flujo de facturación.",
                ['case_code' => $case->case_code],
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function overdueCollection(CarbonInterface $now): Collection
    {
        return BillingRecord::query()
            ->with('case')
            ->whereNotIn('invoice_status', [
                'no_facturable',
                'costo_cero_pendiente_aprobacion',
                'costo_cero_aprobado',
                'pendiente_valorizacion',
            ])
            ->whereColumn('amount_paid', '<', 'amount')
            ->whereDate('due_date', '<', $now->toDateString())
            ->get()
            ->map(fn (BillingRecord $billing): array => $this->candidate(
                $billing,
                $billing->due_date?->startOfDay() ?? $billing->created_at ?? $now,
                $billing->due_date?->startOfDay() ?? $now,
                'Cobranza vencida',
                'Existe un saldo pendiente con fecha de vencimiento superada.',
                [
                    'case_code' => $billing->case?->case_code,
                    'balance' => $billing->balance,
                ],
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function zeroCostPendingApproval(CarbonInterface $now): Collection
    {
        return Approval::query()
            ->with('case')
            ->where('type', 'cost_zero')
            ->where('status', 'pendiente_aprobacion')
            ->get()
            ->map(fn (Approval $approval): array => $this->candidate(
                $approval,
                $approval->created_at ?? $now,
                $this->targetDue($approval->created_at ?? $now, 1440),
                'Costo cero pendiente de aprobación',
                'La solicitud de costo cero requiere una decisión autorizada.',
                ['case_code' => $approval->case?->case_code],
            ));
    }

    /** @return array<string, mixed> */
    private function candidate(
        Model $entity,
        CarbonInterface $detectedAt,
        CarbonInterface $dueAt,
        string $title,
        string $description,
        array $metadata,
    ): array {
        return [
            'alertable_type' => $entity::class,
            'alertable_id' => $entity->getKey(),
            'detected_at' => $detectedAt,
            'due_at' => $dueAt,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
        ];
    }

    private function targetDue(CarbonInterface $start, int $minutes): CarbonInterface
    {
        return $start->copy()->addMinutes($minutes);
    }

    private function activeAlertFor(OperationalSla $sla, string $type, int|string $id): ?OperationalAlert
    {
        return OperationalAlert::query()
            ->where('operational_sla_id', $sla->id)
            ->where('alertable_type', $type)
            ->where('alertable_id', $id)
            ->whereIn('status', ['open', 'acknowledged', 'expired'])
            ->latest('id')
            ->first();
    }

    /** @param array<string, mixed> $candidate */
    private function createAlert(OperationalSla $sla, array $candidate, CarbonInterface $now): OperationalAlert
    {
        $responsibleRole = collect($sla->responsible_roles ?? [])->first();
        $responsibleUser = $responsibleRole
            ? User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', $responsibleRole))
                ->where('active', true)
                ->orderBy('id')
                ->first()
            : null;
        $alert = OperationalAlert::create([
            'operational_sla_id' => $sla->id,
            'alertable_type' => $candidate['alertable_type'],
            'alertable_id' => $candidate['alertable_id'],
            'alert_code' => $sla->code.':'.$candidate['alertable_type'].':'.$candidate['alertable_id'],
            'module' => $sla->module,
            'title' => $candidate['title'],
            'description' => $candidate['description'],
            'priority' => $sla->priority,
            'status' => 'open',
            'responsible_role' => $responsibleRole,
            'responsible_user_id' => $responsibleUser?->id,
            'due_at' => $candidate['due_at'],
            'detected_at' => $candidate['detected_at'] ?? $now,
            'metadata' => $candidate['metadata'],
        ]);
        $alert->load('responsibleUser');

        $this->auditLogger->record('alert.created', $alert, [], [
            'alert_code' => $alert->alert_code,
            'priority' => $alert->priority,
            'responsible_role' => $alert->responsible_role,
            'responsible_user_id' => $alert->responsible_user_id,
        ]);

        return $alert;
    }

    private function expireOpenAlerts(CarbonInterface $now): int
    {
        $expired = 0;
        OperationalAlert::query()
            ->whereIn('status', OperationalAlert::OPEN_STATUSES)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->chunkById(100, function (Collection $alerts) use (&$expired): void {
                foreach ($alerts as $alert) {
                    $alert->update(['status' => 'expired']);
                    $this->auditLogger->record('alert.expired', $alert, ['status' => 'open'], [
                        'status' => 'expired',
                        'alert_code' => $alert->alert_code,
                    ]);
                    $expired++;
                }
            });

        return $expired;
    }

    private function changeStatus(
        OperationalAlert $alert,
        User $user,
        string $status,
        string $auditAction,
        ?string $note,
    ): OperationalAlert {
        return DB::transaction(function () use ($alert, $user, $status, $auditAction, $note): OperationalAlert {
            $lockedAlert = OperationalAlert::query()->lockForUpdate()->findOrFail($alert->id);

            if (! in_array($lockedAlert->status, ['open', 'acknowledged', 'expired'], true)) {
                throw new DomainException('La alerta ya no está disponible para esta acción.');
            }

            $before = $lockedAlert->only(['status', 'acknowledged_at', 'resolved_at', 'dismissed_at', 'resolved_by', 'metadata']);
            $metadata = $lockedAlert->metadata ?? [];
            if (filled($note)) {
                $metadata['last_action_note'] = $note;
            }

            $lockedAlert->update([
                'status' => $status,
                'acknowledged_at' => $status === 'acknowledged' ? now() : $lockedAlert->acknowledged_at,
                'resolved_at' => in_array($status, ['resolved', 'dismissed'], true) ? now() : $lockedAlert->resolved_at,
                'dismissed_at' => $status === 'dismissed' ? now() : $lockedAlert->dismissed_at,
                'resolved_by' => in_array($status, ['resolved', 'dismissed'], true) ? $user->id : $lockedAlert->resolved_by,
                'metadata' => $metadata,
            ]);

            $this->auditLogger->record($auditAction, $lockedAlert, $before, [
                'status' => $status,
                'user_id' => $user->id,
                'note' => $note,
            ]);

            return $lockedAlert->refresh();
        });
    }
}
