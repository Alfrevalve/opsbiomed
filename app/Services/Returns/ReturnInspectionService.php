<?php

namespace App\Services\Returns;

use App\Enums\InventoryStatus;
use App\Enums\WarehouseType;
use App\Models\CaseReturn;
use App\Models\Failure;
use App\Models\InventoryLot;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Traceability\TraceCodeService;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReturnInspectionService
{
    /** @var list<string> */
    private const TECHNICAL_RESULTS = [
        'apto_para_retorno',
        'falla_detectada',
        'no_reutilizable',
        'baja',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TraceCodeService $traceCodeService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function inspect(CaseReturn $caseReturn, array $data): CaseReturn
    {
        return DB::transaction(function () use ($caseReturn, $data): CaseReturn {
            $return = CaseReturn::query()
                ->lockForUpdate()
                ->findOrFail($caseReturn->id);

            if ($return->condition !== 'pendiente_inspeccion') {
                throw new DomainException('Esta devolucion ya fue inspeccionada.');
            }

            $result = (string) $data['inspection_result'];
            $user = auth()->user();

            if (! $user instanceof User) {
                throw new DomainException('La inspeccion requiere un usuario autenticado.');
            }

            if (in_array($result, self::TECHNICAL_RESULTS, true) && ! $user->can('returns.release')) {
                throw new DomainException('Esta decision requiere el permiso de liberacion tecnica.');
            }

            if (in_array($result, self::TECHNICAL_RESULTS, true) && ! $user->hasAnyRole(['Administrador', 'Direccion Tecnica'])) {
                throw new DomainException('Solo Administrador o Direccion Tecnica puede aplicar esta decision.');
            }

            $lot = InventoryLot::query()
                ->with(['product', 'warehouse'])
                ->lockForUpdate()
                ->findOrFail($return->inventory_lot_id);
            if (filled($data['inventory_lot_trace_code'] ?? null)) {
                $this->traceCodeService->record('scanned', $lot, $return->case_id, [
                    'source' => 'return_inspection',
                    'return_id' => $return->id,
                ]);
            }
            $inspectionDate = now()->parse((string) $data['inspection_date']);
            $beforeReturn = $this->returnSnapshot($return);
            $technicalFailure = null;

            $condition = match ($result) {
                'apto_para_retorno' => $this->releaseLot($lot),
                'requiere_limpieza' => $this->quarantineLot($lot, 'Devolucion requiere limpieza e inspeccion.'),
                'requiere_revision_tecnica' => $this->quarantineLot($lot, 'Devolucion requiere revision tecnica.'),
                'falla_detectada' => $this->blockLot($lot, 'Bloqueo por falla detectada en inspeccion de devolucion.'),
                'no_reutilizable' => $this->retireLot($lot, 'Devolucion no reutilizable.'),
                'baja' => $this->retireLot($lot, 'Devolucion dada de baja.'),
                default => throw new DomainException('Resultado de inspeccion no valido.'),
            };

            if ($result === 'falla_detectada') {
                $technicalFailure = Failure::create([
                    'case_id' => $return->case_id,
                    'inventory_lot_id' => $lot->id,
                    'product_id' => $lot->product_id,
                    'failure_type' => 'otro',
                    'occurrence_moment' => 'despues_cirugia',
                    'severity' => 'alta',
                    'status' => 'bloqueada',
                    'preventive_block' => true,
                    'description' => 'Falla detectada en inspeccion de devolucion: '.$data['inspection_observations'],
                    'action_taken' => $data['inspection_observations'],
                    'evidence_reference' => $data['inspection_evidence_reference'] ?? null,
                    'reported_by' => $user->id,
                    'responsible_technical_id' => $data['inspection_responsible_id'],
                ]);
                $this->auditLogger->record('failure.reported', $technicalFailure, [], $this->failureSnapshot($technicalFailure));
                $this->traceCodeService->record('failed', $technicalFailure, $return->case_id, [
                    'inventory_lot_id' => $lot->id,
                    'source' => 'return_inspection',
                ]);
            }

            $return->update([
                'condition' => $condition,
                'inspection_result' => $result,
                'inspection_observations' => $data['inspection_observations'],
                'inspection_evidence_reference' => $data['inspection_evidence_reference'] ?? null,
                'inspection_responsible_id' => $data['inspection_responsible_id'],
                'inspection_date' => $inspectionDate,
                'inspected_by' => $user->id,
                'inspected_at' => $inspectionDate,
                'technical_failure_id' => $technicalFailure?->id,
                'non_reusable_reason' => in_array($result, ['no_reutilizable', 'baja'], true)
                    ? $data['inspection_observations']
                    : null,
            ]);

            $this->auditLogger->record('return.inspected', $return, $beforeReturn, $this->returnSnapshot($return));
            $this->traceCodeService->record('inspected', $return, $return->case_id, [
                'inspection_result' => $result,
                'condition' => $condition,
            ]);

            if ($condition === 'liberado') {
                $this->traceCodeService->record('released', $lot, $return->case_id, [
                    'source' => 'return_inspection',
                    'return_id' => $return->id,
                ]);
            }

            if ($condition === 'bloqueado') {
                $this->traceCodeService->record('blocked', $lot, $return->case_id, [
                    'source' => 'return_inspection',
                    'return_id' => $return->id,
                ]);
            }

            return $return->fresh([
                'case.institution',
                'case.doctor',
                'case.patient',
                'inventoryLot.product',
                'inventoryLot.warehouse',
                'inspectedBy',
                'inspectionResponsible',
                'technicalFailure',
            ]);
        });
    }

    private function releaseLot(InventoryLot $lot): string
    {
        if ($lot->expiry?->isBefore(today())) {
            throw new DomainException('No se puede liberar una devolucion de un lote vencido.');
        }

        if (in_array($lot->status, [InventoryStatus::Desvalorizado, InventoryStatus::Bloqueado, InventoryStatus::FallaPreventiva], true)) {
            throw new DomainException('El lote mantiene un bloqueo o desvalorizacion y requiere liberacion tecnica independiente.');
        }

        if ($this->hasOpenFailure($lot)) {
            throw new DomainException('El lote tiene una falla abierta y no puede volver a disponible.');
        }

        $before = $this->lotSnapshot($lot);
        $lot->update([
            'status' => InventoryStatus::Apto,
            'eligible_flag' => $lot->warehouse?->active === true
                && $lot->warehouse?->type !== WarehouseType::Desvalorizado
                && (bool) $lot->warehouse?->counts_as_immediate
                && (int) $lot->quantity > 0,
            'block_reason' => null,
        ]);
        $this->auditLogger->record('inventory.released', $lot, $before, $this->lotSnapshot($lot));

        return 'liberado';
    }

    private function quarantineLot(InventoryLot $lot, string $reason): string
    {
        $before = $this->lotSnapshot($lot);
        $lot->update([
            'status' => InventoryStatus::Cuarentena,
            'eligible_flag' => false,
            'block_reason' => $reason,
        ]);
        $this->auditLogger->record('inventory.quarantined', $lot, $before, $this->lotSnapshot($lot));

        return 'cuarentena';
    }

    private function blockLot(InventoryLot $lot, string $reason): string
    {
        $before = $this->lotSnapshot($lot);
        $lot->update([
            'status' => InventoryStatus::FallaPreventiva,
            'eligible_flag' => false,
            'block_reason' => $reason,
        ]);
        $this->auditLogger->record('inventory.blocked', $lot, $before, $this->lotSnapshot($lot));

        return 'bloqueado';
    }

    private function retireLot(InventoryLot $lot, string $reason): string
    {
        $before = $this->lotSnapshot($lot);
        $lot->update([
            'status' => InventoryStatus::Desvalorizado,
            'eligible_flag' => false,
            'block_reason' => $reason,
        ]);
        $this->auditLogger->record('inventory.retired', $lot, $before, $this->lotSnapshot($lot));

        return $reason === 'Devolucion dada de baja.' ? 'dado_de_baja' : 'desvalorizado';
    }

    private function hasOpenFailure(InventoryLot $lot): bool
    {
        return $lot->failures()
            ->whereIn('status', ['reportada', 'bloqueada', 'en_revision', 'pendiente_repuesto'])
            ->exists();
    }

    /** @return array<string, mixed> */
    private function returnSnapshot(CaseReturn $return): array
    {
        return $return->only([
            'id',
            'case_id',
            'inventory_lot_id',
            'returned_qty',
            'condition',
            'inspection_result',
            'inspection_observations',
            'inspection_evidence_reference',
            'inspection_responsible_id',
            'inspection_date',
            'inspected_by',
            'inspected_at',
            'technical_failure_id',
            'non_reusable_reason',
        ]);
    }

    /** @return array<string, mixed> */
    private function lotSnapshot(InventoryLot $lot): array
    {
        return [
            'id' => $lot->id,
            'quantity' => $lot->quantity,
            'status' => $lot->status?->value,
            'eligible_flag' => $lot->eligible_flag,
            'block_reason' => $lot->block_reason,
        ];
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
            'reported_by',
            'responsible_technical_id',
        ]);
    }
}
