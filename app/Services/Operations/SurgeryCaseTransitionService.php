<?php

namespace App\Services\Operations;

use App\Enums\CaseStatus;
use App\Models\AuditLog;
use App\Models\CaseReturn;
use App\Models\DocumentEvidence;
use App\Models\Failure;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SurgeryCaseTransitionService
{
    private const OPEN_FAILURE_STATUSES = [
        'reportada',
        'bloqueada',
        'en_revision',
        'pendiente_repuesto',
    ];

    private const RESOLVED_RETURN_CONDITIONS = [
        'liberado',
        'desvalorizado',
        'dado_de_baja',
    ];

    private const SETTLED_INVOICE_STATUSES = [
        'facturado',
        'no_facturable',
        'costo_cero_aprobado',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SurgeryCasePreparationService $preparationService,
    ) {}

    /** @return list<string> */
    public static function selectableStatusValues(): array
    {
        return [
            CaseStatus::Programada->value,
            CaseStatus::Reservado->value,
            CaseStatus::Preparacion->value,
            CaseStatus::Internado->value,
            CaseStatus::EnSala->value,
            CaseStatus::EnCirugia->value,
            CaseStatus::PendienteCierre->value,
            CaseStatus::Conciliacion->value,
            CaseStatus::Facturacion->value,
            CaseStatus::Facturada->value,
            CaseStatus::Cancelado->value,
        ];
    }

    public function canOverride(User $user): bool
    {
        return $user->hasAnyRole(['Administrador', 'Jefe de Linea']);
    }

    /**
     * @return Collection<int, array{value: string, label: string, requires_observation: bool, requires_override: bool}>
     */
    public function availableTransitions(SurgeryCase $case, User $user): Collection
    {
        $case = $this->loadContext($case);
        $canOverride = $this->canOverride($user);
        $targets = collect($this->normalTargets($case));

        if ($this->canCancel($case)) {
            $targets->push(CaseStatus::Cancelado);
        }

        if ($canOverride) {
            $targets = $targets->merge($this->overrideTargets($case));
        }

        return $targets
            ->unique(fn (CaseStatus $status): string => $status->value)
            ->map(function (CaseStatus $target) use ($case, $canOverride): ?array {
                $isNormal = $this->isNormalTarget($case, $target);
                $requiresOverride = ! $isNormal;
                $blockReason = $this->transitionBlockReason($case, $target, $requiresOverride);

                if ($blockReason !== null && $canOverride && $isNormal) {
                    $requiresOverride = true;
                    $blockReason = $this->transitionBlockReason($case, $target, true);
                }

                if ($blockReason !== null) {
                    return null;
                }

                return [
                    'value' => $target->value,
                    'label' => $target->operationalLabel(),
                    'requires_observation' => $this->requiresObservation($case, $target, $requiresOverride),
                    'requires_override' => $requiresOverride,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<array{label: string, state: string}>
     */
    public function timeline(SurgeryCase $case): array
    {
        $case = $this->loadContext($case);
        $stages = [
            ['label' => 'Solicitud registrada'],
            ['label' => 'Programada'],
            ['label' => 'Reservada'],
            ['label' => 'Preparada'],
            ['label' => 'Internada'],
            ['label' => 'En sala'],
            ['label' => 'En cirugia'],
            ['label' => 'Cx finalizada'],
            ['label' => 'Cerrada'],
            ['label' => 'Retorno pendiente'],
            ['label' => 'Inspeccionada'],
            ['label' => 'Facturacion'],
            ['label' => 'Cerrada total'],
        ];

        if ($case->status === CaseStatus::Cancelado) {
            return collect($stages)
                ->map(fn (array $stage, int $index): array => $stage + [
                    'state' => $index === 0 ? 'completed' : 'blocked',
                ])
                ->all();
        }

        $currentIndex = $this->timelineIndex($case);
        $hasPendingReturns = $this->hasPendingReturns($case);
        $hasOpenFailures = $this->hasOpenFailures($case);
        $hasRequiredDocumentsPending = $this->hasRequiredDocumentsPending($case);
        $reconciliationComplete = $this->reconciliationComplete($case);
        $valuationComplete = $this->valuationComplete($case);
        $finalBlockers = $this->finalBlockers($case);

        return collect($stages)
            ->map(function (array $stage, int $index) use (
                $case,
                $currentIndex,
                $hasPendingReturns,
                $hasOpenFailures,
                $hasRequiredDocumentsPending,
                $reconciliationComplete,
                $valuationComplete,
                $finalBlockers,
            ): array {
                $state = $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'current' : 'pending');

                if ($index === 9 && $hasPendingReturns && $currentIndex >= 9) {
                    $state = 'current';
                }

                if ($index === 10 && (! $reconciliationComplete || $hasOpenFailures) && $currentIndex >= 10) {
                    $state = 'blocked';
                }

                if ($index === 11 && (! $valuationComplete || $hasRequiredDocumentsPending) && $currentIndex >= 11) {
                    $state = 'blocked';
                }

                if ($index === 12 && $finalBlockers !== [] && $currentIndex >= 12) {
                    $state = 'blocked';
                }

                if ($case->status === CaseStatus::Observado && $index === $currentIndex) {
                    $state = 'blocked';
                }

                return $stage + ['state' => $state];
            })
            ->all();
    }

    /** @return Collection<int, AuditLog> */
    public function recentTransitions(SurgeryCase $case): Collection
    {
        return AuditLog::query()
            ->with('user')
            ->where('auditable_type', SurgeryCase::class)
            ->where('auditable_id', $case->id)
            ->where('action', 'case.status_changed')
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    public function nextStepMessage(SurgeryCase $case): ?string
    {
        $case = $this->loadContext($case);

        if ($case->status === CaseStatus::Cancelado) {
            return 'El caso esta cancelado. Registre una nueva solicitud si la cirugia se reprograma.';
        }

        if ($case->status === CaseStatus::Facturada) {
            return $this->finalBlockers($case) === []
                ? 'El ciclo operativo y administrativo del caso esta cerrado.'
                : 'El cierre total fue autorizado con pendientes que siguen visibles en las alertas del caso.';
        }

        if ($case->status === CaseStatus::PendienteCierre) {
            return 'Registre el cierre quirurgico para conciliar consumo, devoluciones, incidencias y valorizacion.';
        }

        if (in_array($case->status, [
            CaseStatus::Reservado,
            CaseStatus::Reservada,
            CaseStatus::Preparacion,
            CaseStatus::PreoperatorioConfirmado,
            CaseStatus::Internado,
        ], true) && ! $this->preparationService->isReadyForRoom($case)) {
            return 'Complete el checklist preoperatorio, el despacho exacto por reserva y la guia o evidencia antes de avanzar.';
        }

        if ($this->hasPendingReturns($case)) {
            return $this->hasLatePendingReturn($case)
                ? 'Hay una devolucion pendiente por mas de 24 horas. La inspeccion requiere una observacion antes de avanzar.'
                : 'Las devoluciones pendientes deben inspeccionarse antes de continuar con la conciliacion.';
        }

        if (in_array($case->status, [CaseStatus::Cerrado, CaseStatus::Cerrada], true)
            && $this->hasDelayedReturnInspection($case)) {
            return 'Una devolucion fue inspeccionada despues de 24 horas. Registre una observacion al avanzar a conciliacion.';
        }

        if (in_array($case->status, [CaseStatus::Cerrado, CaseStatus::Cerrada], true) && ! $this->reconciliationComplete($case)) {
            return 'Complete la conciliacion del consumo antes de avanzar a facturacion.';
        }

        if ($case->status === CaseStatus::Conciliacion && ! $this->valuationComplete($case)) {
            return 'La valorizacion debe estar completa antes de pasar a facturacion.';
        }

        if ($case->status === CaseStatus::Facturacion && $this->finalBlockers($case) !== []) {
            return 'El cierre total requiere resolver los pendientes operativos y financieros, o un override autorizado y observado.';
        }

        if ($case->status === CaseStatus::Programada && ! $this->hasActiveReservation($case)) {
            return 'Registre al menos una reserva activa antes de preparar el caso para atencion.';
        }

        return 'Seleccione el siguiente estado permitido y registre una observacion cuando corresponda.';
    }

    /**
     * @param  array{target_status: string, observation?: ?string, override?: bool}  $data
     */
    public function transition(SurgeryCase $surgeryCase, array $data, User $user): SurgeryCase
    {
        return DB::transaction(function () use ($surgeryCase, $data, $user): SurgeryCase {
            $case = SurgeryCase::query()
                ->with([
                    'reservations',
                    'preparation',
                    'materialsSent',
                    'returns',
                    'failures',
                    'documents',
                    'valuation.approval',
                    'billingRecord',
                    'reconciliation',
                ])
                ->lockForUpdate()
                ->findOrFail($surgeryCase->id);
            $target = CaseStatus::from((string) $data['target_status']);
            $observation = $data['observation'] ?? null;
            $override = (bool) ($data['override'] ?? false);

            if ($case->status === $target) {
                throw new DomainException('El caso ya se encuentra en el estado operativo seleccionado.');
            }

            if ($override && ! $this->canOverride($user)) {
                throw new DomainException('Solo Administrador o Jefe de Linea puede aplicar un override de estado.');
            }

            $blockReason = $this->transitionBlockReason($case, $target, $override);
            if ($blockReason !== null) {
                throw new DomainException($blockReason);
            }

            $isNormalTarget = $this->isNormalTarget($case, $target);
            if (! $isNormalTarget && ! $override) {
                throw new DomainException('Solo se puede avanzar al siguiente estado operativo permitido.');
            }

            if (! $isNormalTarget && ! $this->canOverrideTarget($case, $target)) {
                throw new DomainException('Este avance debe completarse desde su modulo operativo especializado.');
            }

            if ($this->requiresObservation($case, $target, $override) && blank($observation)) {
                throw new DomainException('Esta transicion requiere una observacion registrada.');
            }

            $before = [
                'status' => $case->status->value,
                'label' => $case->status->operationalLabel(),
            ];
            $case->update(['status' => $target]);
            $this->auditLogger->record('case.status_changed', $case, $before, [
                'previous_status' => $before['status'],
                'previous_label' => $before['label'],
                'new_status' => $target->value,
                'new_label' => $target->operationalLabel(),
                'observation' => $observation,
                'override' => $override,
                'user_id' => $user->id,
            ]);

            return $case->refresh();
        });
    }

    /** @return list<CaseStatus> */
    private function normalTargets(SurgeryCase $case): array
    {
        return match ($case->status) {
            CaseStatus::SolicitudRegistrada, CaseStatus::Solicitado => [CaseStatus::Programada],
            CaseStatus::Programada, CaseStatus::ValidacionComercial, CaseStatus::Reprogramado => [CaseStatus::Reservado],
            CaseStatus::Reservado, CaseStatus::Reservada => [CaseStatus::Preparacion],
            CaseStatus::Preparacion, CaseStatus::PreoperatorioConfirmado => [CaseStatus::Internado],
            CaseStatus::Internado => [CaseStatus::EnSala],
            CaseStatus::EnSala => [CaseStatus::EnCirugia, CaseStatus::PendienteCierre],
            CaseStatus::EnCirugia => [CaseStatus::PendienteCierre],
            CaseStatus::Cerrado, CaseStatus::Cerrada => [CaseStatus::Conciliacion],
            CaseStatus::Conciliacion => [CaseStatus::Facturacion],
            CaseStatus::Facturacion => [CaseStatus::Facturada],
            default => [],
        };
    }

    /** @return list<CaseStatus> */
    private function overrideTargets(SurgeryCase $case): array
    {
        return collect([
            CaseStatus::Programada,
            CaseStatus::Preparacion,
            CaseStatus::Internado,
            CaseStatus::EnSala,
            CaseStatus::EnCirugia,
        ])
            ->filter(fn (CaseStatus $target): bool => $this->canOverrideTarget($case, $target))
            ->values()
            ->all();
    }

    private function isNormalTarget(SurgeryCase $case, CaseStatus $target): bool
    {
        if ($target === CaseStatus::Cancelado) {
            return $this->canCancel($case);
        }

        return in_array($target, $this->normalTargets($case), true);
    }

    private function canOverrideTarget(SurgeryCase $case, CaseStatus $target): bool
    {
        if (! in_array($target, [
            CaseStatus::Programada,
            CaseStatus::Preparacion,
            CaseStatus::Internado,
            CaseStatus::EnSala,
            CaseStatus::EnCirugia,
        ], true)) {
            return false;
        }

        return $this->statusRank($target) > $this->statusRank($case->status)
            && $this->statusRank($case->status) < $this->statusRank(CaseStatus::PendienteCierre);
    }

    private function transitionBlockReason(SurgeryCase $case, CaseStatus $target, bool $override): ?string
    {
        if (in_array($target, [
            CaseStatus::Preparacion,
            CaseStatus::Internado,
            CaseStatus::EnSala,
            CaseStatus::EnCirugia,
        ], true) && ! $this->preparationService->isReadyForRoom($case)) {
            return 'Complete el checklist preoperatorio, el despacho exacto por reserva y la guia o evidencia antes de avanzar a esta etapa.';
        }

        if (in_array($target, [CaseStatus::Reservado, CaseStatus::EnSala, CaseStatus::EnCirugia], true)
            && ! $this->hasActiveReservation($case)) {
            return 'No se puede avanzar sin al menos una reserva activa de material.';
        }

        if ($target === CaseStatus::PendienteCierre
            && ! in_array($case->status, [CaseStatus::EnSala, CaseStatus::EnCirugia], true)) {
            return 'La cirugia solo puede finalizarse desde En sala o En cirugia.';
        }

        if ($target === CaseStatus::Cancelado) {
            if (! $this->canCancel($case)) {
                return 'El caso no puede cancelarse en el estado operativo actual.';
            }

            if ($this->hasActiveReservation($case)) {
                return 'No se puede cancelar mientras existan reservas activas. Libere el material antes de cancelar el caso.';
            }
        }

        if ($target === CaseStatus::Conciliacion) {
            if (! in_array($case->status, [CaseStatus::Cerrado, CaseStatus::Cerrada], true)) {
                return 'La conciliacion se habilita despues del cierre quirurgico.';
            }

            if ($this->hasPendingReturns($case)) {
                return 'No se puede avanzar: existen devoluciones pendientes de inspeccion.';
            }

            if (! $this->reconciliationComplete($case)) {
                return 'Complete la conciliacion de consumo antes de actualizar este estado.';
            }
        }

        if ($target === CaseStatus::Facturacion) {
            if ($case->status !== CaseStatus::Conciliacion) {
                return 'La facturacion se habilita despues de la conciliacion inspeccionada.';
            }

            if (! $this->reconciliationComplete($case)) {
                return 'La conciliacion debe estar completa antes de avanzar a facturacion.';
            }

            if (! $this->valuationComplete($case) && ! $override) {
                return 'La valorizacion no esta completa. Un override autorizado requiere observacion.';
            }
        }

        if ($target === CaseStatus::Facturada) {
            if ($case->status !== CaseStatus::Facturacion) {
                return 'El cierre total se habilita desde la etapa de facturacion.';
            }

            if ($this->finalBlockers($case) !== [] && ! $override) {
                return 'No se puede cerrar totalmente mientras existan pendientes operativos o financieros.';
            }
        }

        return null;
    }

    private function requiresObservation(SurgeryCase $case, CaseStatus $target, bool $override): bool
    {
        return $override
            || $target === CaseStatus::Cancelado
            || ($target === CaseStatus::Conciliacion && $this->hasDelayedReturnInspection($case))
            || ($target === CaseStatus::Facturacion && ! $this->valuationComplete($case))
            || ($target === CaseStatus::Facturada && $this->finalBlockers($case) !== []);
    }

    private function canCancel(SurgeryCase $case): bool
    {
        return ! in_array($case->status, [
            CaseStatus::PendienteCierre,
            CaseStatus::Cerrado,
            CaseStatus::Cerrada,
            CaseStatus::Conciliacion,
            CaseStatus::Facturacion,
            CaseStatus::Facturada,
            CaseStatus::Cancelado,
        ], true);
    }

    private function hasActiveReservation(SurgeryCase $case): bool
    {
        return $case->reservations->contains(
            fn (Reservation $reservation): bool => $reservation->status === 'active',
        );
    }

    private function hasPendingReturns(SurgeryCase $case): bool
    {
        return $case->returns->contains(
            fn (CaseReturn $return): bool => ! in_array($return->condition, self::RESOLVED_RETURN_CONDITIONS, true),
        );
    }

    private function hasLatePendingReturn(SurgeryCase $case): bool
    {
        return $case->returns->contains(function (CaseReturn $return): bool {
            return ! in_array($return->condition, self::RESOLVED_RETURN_CONDITIONS, true)
                && $return->created_at?->lte(now()->subHours(24)) === true;
        });
    }

    private function hasDelayedReturnInspection(SurgeryCase $case): bool
    {
        return $case->returns->contains(function (CaseReturn $return): bool {
            $inspectionDeadline = $return->created_at?->copy()->addHours(24);

            return $inspectionDeadline !== null
                && $return->inspected_at?->gt($inspectionDeadline) === true;
        });
    }

    private function hasOpenFailures(SurgeryCase $case): bool
    {
        return $case->failures->contains(
            fn (Failure $failure): bool => in_array($failure->status, self::OPEN_FAILURE_STATUSES, true),
        );
    }

    private function hasRequiredDocumentsPending(SurgeryCase $case): bool
    {
        return $case->documents->contains(
            fn (DocumentEvidence $document): bool => $document->is_required && $document->status !== 'validado',
        );
    }

    private function reconciliationComplete(SurgeryCase $case): bool
    {
        return $case->reconciliation?->status === 'conciliado';
    }

    private function valuationComplete(SurgeryCase $case): bool
    {
        return $case->valuation !== null
            && ! in_array($case->valuation->status, [
                'pendiente_valorizacion',
                'costo_cero_pendiente_aprobacion',
            ], true)
            && $case->valuation->approval?->status !== 'pendiente_aprobacion';
    }

    /** @return list<string> */
    private function finalBlockers(SurgeryCase $case): array
    {
        $blockers = [];

        if ($this->hasOpenFailures($case)) {
            $blockers[] = 'fallas tecnicas abiertas';
        }

        if ($this->hasPendingReturns($case)) {
            $blockers[] = 'devoluciones pendientes de inspeccion';
        }

        if ($this->hasRequiredDocumentsPending($case)) {
            $blockers[] = 'documentos obligatorios pendientes de validar';
        }

        if (! $this->reconciliationComplete($case)) {
            $blockers[] = 'conciliacion pendiente';
        }

        if (! $this->valuationComplete($case)) {
            $blockers[] = 'valorizacion pendiente';
        }

        if ($case->billingRecord === null) {
            $blockers[] = 'registro de facturacion pendiente';

            return $blockers;
        }

        if (! in_array($case->billingRecord->invoice_status, self::SETTLED_INVOICE_STATUSES, true)) {
            $blockers[] = 'facturacion pendiente';
        }

        if ($case->billingRecord->balance > 0) {
            $blockers[] = $case->billingRecord->is_overdue
                ? 'deuda vencida'
                : 'deuda pendiente';
        }

        return $blockers;
    }

    private function timelineIndex(SurgeryCase $case): int
    {
        return match ($case->status) {
            CaseStatus::SolicitudRegistrada, CaseStatus::Solicitado => 0,
            CaseStatus::Programada, CaseStatus::ValidacionComercial, CaseStatus::Reprogramado, CaseStatus::Observado => 1,
            CaseStatus::Reservado, CaseStatus::Reservada => 2,
            CaseStatus::Preparacion, CaseStatus::PreoperatorioConfirmado => 3,
            CaseStatus::Internado => 4,
            CaseStatus::EnSala => 5,
            CaseStatus::EnCirugia => 6,
            CaseStatus::PendienteCierre => 7,
            CaseStatus::Cerrado, CaseStatus::Cerrada => $this->hasPendingReturns($case)
                ? 9
                : ($this->reconciliationComplete($case) ? 10 : 8),
            CaseStatus::Conciliacion => 10,
            CaseStatus::Facturacion => 11,
            CaseStatus::Facturada => 12,
            CaseStatus::Cancelado => 0,
        };
    }

    private function statusRank(CaseStatus $status): int
    {
        return match ($status) {
            CaseStatus::SolicitudRegistrada, CaseStatus::Solicitado => 0,
            CaseStatus::Programada, CaseStatus::ValidacionComercial, CaseStatus::Reprogramado, CaseStatus::Observado => 1,
            CaseStatus::Reservado, CaseStatus::Reservada => 2,
            CaseStatus::Preparacion, CaseStatus::PreoperatorioConfirmado => 3,
            CaseStatus::Internado => 4,
            CaseStatus::EnSala => 5,
            CaseStatus::EnCirugia => 6,
            CaseStatus::PendienteCierre => 7,
            CaseStatus::Cerrado, CaseStatus::Cerrada => 8,
            CaseStatus::Conciliacion => 10,
            CaseStatus::Facturacion => 11,
            CaseStatus::Facturada => 12,
            CaseStatus::Cancelado => -1,
        };
    }

    private function loadContext(SurgeryCase $case): SurgeryCase
    {
        $case->loadMissing([
            'reservations',
            'preparation',
            'materialsSent',
            'returns',
            'failures',
            'documents',
            'valuation.approval',
            'billingRecord',
            'reconciliation',
        ]);

        return $case;
    }
}
