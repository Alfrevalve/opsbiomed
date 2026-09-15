<?php

namespace App\Services\Failures;

use App\Enums\FailureSeverity;
use App\Enums\FailureStatus;
use App\Enums\InventoryStatus;
use App\Enums\WarehouseType;
use App\Models\Failure;
use App\Models\InventoryLot;
use App\Services\Audit\AuditLogger;
use App\Services\Traceability\TraceCodeService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FailureManagementService
{
    /** @var list<string> */
    private const OPEN_STATUSES = [
        'reportada',
        'bloqueada',
        'en_revision',
        'pendiente_repuesto',
    ];

    /** @var list<string> */
    private const TERMINAL_STATUSES = [
        'liberada',
        'dada_de_baja',
        'cerrada',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TraceCodeService $traceCodeService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function report(array $data): Failure
    {
        return DB::transaction(function () use ($data): Failure {
            $lot = $this->lockedLot($data['inventory_lot_id'] ?? null);
            $productId = $lot?->product_id ?? ($data['product_id'] ?? null);
            $preventiveBlock = $this->requiresPreventiveBlock($data['severity'], $data['occurrence_moment']);

            $failure = Failure::create([
                'case_id' => $data['case_id'] ?? null,
                'inventory_lot_id' => $lot?->id,
                'product_id' => $productId,
                'failure_type' => $data['failure_type'],
                'occurrence_moment' => $data['occurrence_moment'],
                'severity' => $data['severity'],
                'status' => $preventiveBlock ? FailureStatus::Bloqueada->value : FailureStatus::Reportada->value,
                'preventive_block' => $preventiveBlock,
                'description' => $data['description'],
                'action_taken' => $data['action_taken'] ?? null,
                'evidence_reference' => $data['evidence_reference'] ?? null,
                'reported_by' => auth()->id(),
                'responsible_technical_id' => $data['responsible_technical_id'] ?? null,
            ]);

            $this->auditLogger->record('failure.reported', $failure, [], $this->failureSnapshot($failure));
            $this->traceCodeService->record('failed', $failure, $failure->case_id, [
                'severity' => $failure->severity,
                'occurrence_moment' => $failure->occurrence_moment,
            ]);

            if ($preventiveBlock) {
                $this->blockLots($lot, $productId);
                if ($lot !== null) {
                    $this->traceCodeService->record('blocked', $lot, $failure->case_id, [
                        'failure_id' => $failure->id,
                        'reason' => 'Bloqueo preventivo por falla tecnica.',
                    ]);
                }
            }

            return $failure->fresh(['product', 'inventoryLot.warehouse', 'case']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Failure $failure, array $data): Failure
    {
        return DB::transaction(function () use ($failure, $data): Failure {
            $failure = Failure::query()->lockForUpdate()->findOrFail($failure->id);

            $this->ensureNotTerminal($failure);
            if (! in_array($data['status'], FailureStatus::editableValues(), true)) {
                throw new DomainException('Los estados finales solo se alcanzan mediante su accion controlada.');
            }
            $before = $this->failureSnapshot($failure);
            $preventiveBlock = (bool) $failure->preventive_block
                || $this->requiresPreventiveBlock($data['severity'], $data['occurrence_moment']);

            $failure->fill([
                'failure_type' => $data['failure_type'],
                'occurrence_moment' => $data['occurrence_moment'],
                'severity' => $data['severity'],
                'status' => $preventiveBlock ? FailureStatus::Bloqueada->value : $data['status'],
                'preventive_block' => $preventiveBlock,
                'description' => $data['description'],
                'action_taken' => $data['action_taken'] ?? null,
                'evidence_reference' => $data['evidence_reference'] ?? null,
                'responsible_technical_id' => $data['responsible_technical_id'] ?? null,
                'diagnosis' => $data['diagnosis'] ?? null,
                'probable_cause' => $data['probable_cause'] ?? null,
                'corrective_action' => $data['corrective_action'] ?? null,
                'requires_supplier' => (bool) ($data['requires_supplier'] ?? false),
                'requires_replacement' => (bool) ($data['requires_replacement'] ?? false),
            ]);

            if (filled($data['diagnosis'] ?? null) || filled($data['corrective_action'] ?? null)) {
                $failure->reviewed_at = now();
                $failure->reviewed_by = auth()->id();
            }

            $failure->save();
            $this->auditLogger->record('failure.updated', $failure, $before, $this->failureSnapshot($failure));

            if ($preventiveBlock) {
                $this->blockLots($failure->inventoryLot, $failure->product_id);
            }

            return $failure->fresh(['product', 'inventoryLot.warehouse', 'case']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function release(Failure $failure, array $data): Failure
    {
        return DB::transaction(function () use ($failure, $data): Failure {
            $failure = Failure::query()->with('inventoryLot')->lockForUpdate()->findOrFail($failure->id);
            $this->ensureNotTerminal($failure);
            $lots = $this->affectedLots($failure->inventory_lot_id, $failure->product_id);

            foreach ($lots as $lot) {
                if ($lot->expiry?->isBefore(today())) {
                    throw new DomainException('No se puede liberar un lote vencido.');
                }
            }

            $before = $this->failureSnapshot($failure);
            $failure->update([
                'status' => FailureStatus::Liberada->value,
                'preventive_block' => false,
                'diagnosis' => $data['diagnosis'],
                'corrective_action' => $data['corrective_action'],
                'release_notes' => $data['release_notes'],
                'reviewed_at' => $failure->reviewed_at ?? now(),
                'reviewed_by' => $failure->reviewed_by ?? auth()->id(),
                'released_at' => now(),
                'released_by' => auth()->id(),
            ]);
            $this->auditLogger->record('failure.released', $failure, $before, $this->failureSnapshot($failure));
            $this->traceCodeService->record('released', $failure, $failure->case_id, [
                'diagnosis' => $failure->diagnosis,
            ]);

            foreach ($lots as $lot) {
                $this->releaseLotIfSafe($lot, $failure);
                if ($lot->status === InventoryStatus::Apto) {
                    $this->traceCodeService->record('released', $lot, $failure->case_id, [
                        'failure_id' => $failure->id,
                    ]);
                }
            }

            return $failure->fresh(['product', 'inventoryLot.warehouse', 'case']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function retire(Failure $failure, array $data): Failure
    {
        return DB::transaction(function () use ($failure, $data): Failure {
            $failure = Failure::query()->lockForUpdate()->findOrFail($failure->id);
            $this->ensureNotTerminal($failure);
            $before = $this->failureSnapshot($failure);
            $failure->update([
                'status' => FailureStatus::DadaDeBaja->value,
                'preventive_block' => true,
                'retired_at' => now(),
                'retired_by' => auth()->id(),
                'retirement_reason' => $data['retirement_reason'],
                'retirement_evidence' => $data['retirement_evidence'],
            ]);
            $this->auditLogger->record('failure.retired', $failure, $before, $this->failureSnapshot($failure));

            foreach ($this->affectedLots($failure->inventory_lot_id, $failure->product_id) as $lot) {
                $lotBefore = $this->lotSnapshot($lot);
                $lot->update([
                    'status' => InventoryStatus::Desvalorizado,
                    'eligible_flag' => false,
                    'block_reason' => 'Lote dado de baja por falla tecnica.',
                ]);
                $this->auditLogger->record('inventory.retired', $lot, $lotBefore, $this->lotSnapshot($lot));
                $this->traceCodeService->record('blocked', $lot, $failure->case_id, [
                    'failure_id' => $failure->id,
                    'reason' => 'Lote dado de baja por falla tecnica.',
                ]);
            }

            return $failure->fresh(['product', 'inventoryLot.warehouse', 'case']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function close(Failure $failure, array $data): Failure
    {
        return DB::transaction(function () use ($failure, $data): Failure {
            $failure = Failure::query()->lockForUpdate()->findOrFail($failure->id);

            if (! in_array($failure->status, [FailureStatus::Liberada->value, FailureStatus::DadaDeBaja->value], true)) {
                throw new DomainException('Solo una falla liberada o dada de baja puede cerrarse.');
            }

            $before = $this->failureSnapshot($failure);
            $failure->update([
                'status' => FailureStatus::Cerrada->value,
                'action_taken' => $data['action_taken'] ?? $failure->action_taken,
            ]);
            $this->auditLogger->record('failure.closed', $failure, $before, $this->failureSnapshot($failure));

            return $failure->fresh(['product', 'inventoryLot.warehouse', 'case']);
        });
    }

    private function requiresPreventiveBlock(string $severity, string $moment): bool
    {
        return in_array($severity, [FailureSeverity::Alta->value, FailureSeverity::Critica->value], true)
            || $moment === 'durante_cirugia';
    }

    private function ensureNotTerminal(Failure $failure): void
    {
        if (in_array($failure->status, self::TERMINAL_STATUSES, true)) {
            throw new DomainException('La falla ya se encuentra en un estado final.');
        }
    }

    private function lockedLot(?int $lotId): ?InventoryLot
    {
        if ($lotId === null) {
            return null;
        }

        return InventoryLot::query()->with(['product', 'warehouse'])->lockForUpdate()->findOrFail($lotId);
    }

    /**
     * @return Collection<int, InventoryLot>
     */
    private function affectedLots(?int $lotId, ?int $productId): Collection
    {
        if ($lotId !== null) {
            return InventoryLot::query()->with(['product', 'warehouse'])->lockForUpdate()->whereKey($lotId)->get();
        }

        if ($productId === null) {
            return new Collection;
        }

        return InventoryLot::query()
            ->with(['product', 'warehouse'])
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->get();
    }

    private function blockLots(?InventoryLot $selectedLot, ?int $productId): void
    {
        $lots = $selectedLot !== null
            ? new Collection([$selectedLot])
            : $this->affectedLots(null, $productId);

        foreach ($lots as $lot) {
            $lot->refresh()->load(['product', 'warehouse']);
            $before = $this->lotSnapshot($lot);
            $lot->update([
                'status' => in_array($lot->status?->value, [InventoryStatus::Desvalorizado->value, InventoryStatus::Vencido->value, InventoryStatus::Cuarentena->value], true)
                    ? $lot->status
                    : InventoryStatus::FallaPreventiva,
                'eligible_flag' => false,
                'block_reason' => 'Bloqueo preventivo por falla tecnica.',
            ]);
            $this->auditLogger->record('inventory.blocked', $lot, $before, $this->lotSnapshot($lot));
        }
    }

    private function releaseLotIfSafe(InventoryLot $lot, Failure $releasedFailure): void
    {
        $lot->refresh()->load(['product', 'warehouse']);

        if ($lot->status !== InventoryStatus::FallaPreventiva || $this->hasOpenPreventiveFailure($lot->id)) {
            return;
        }

        if ($lot->product?->active !== true || $lot->warehouse?->active !== true || $lot->warehouse?->type === WarehouseType::Desvalorizado || $lot->expiry?->isBefore(today()) || (int) $lot->quantity <= 0) {
            return;
        }

        $before = $this->lotSnapshot($lot);
        $lot->update([
            'status' => InventoryStatus::Apto,
            'eligible_flag' => (bool) $lot->warehouse?->counts_as_immediate,
            'block_reason' => null,
        ]);
        $this->auditLogger->record('inventory.released', $lot, $before, $this->lotSnapshot($lot) + [
            'failure_id' => $releasedFailure->id,
        ]);
    }

    private function hasOpenPreventiveFailure(int $lotId): bool
    {
        return Failure::query()
            ->where('inventory_lot_id', $lotId)
            ->where('preventive_block', true)
            ->whereIn('status', self::OPEN_STATUSES)
            ->exists();
    }

    /** @return array<string, mixed> */
    private function failureSnapshot(Failure $failure): array
    {
        return $failure->only([
            'id',
            'case_id',
            'inventory_lot_id',
            'product_id',
            'failure_type',
            'occurrence_moment',
            'severity',
            'status',
            'preventive_block',
            'description',
            'action_taken',
            'responsible_technical_id',
            'diagnosis',
            'probable_cause',
            'corrective_action',
            'requires_supplier',
            'requires_replacement',
            'reviewed_at',
            'reviewed_by',
            'released_at',
            'released_by',
            'retired_at',
            'retired_by',
        ]);
    }

    /** @return array<string, mixed> */
    private function lotSnapshot(InventoryLot $lot): array
    {
        return [
            'id' => $lot->id,
            'product_id' => $lot->product_id,
            'lot' => $lot->lot,
            'serial' => $lot->serial,
            'quantity' => $lot->quantity,
            'status' => $lot->status?->value,
            'eligible_flag' => $lot->eligible_flag,
            'block_reason' => $lot->block_reason,
        ];
    }
}
