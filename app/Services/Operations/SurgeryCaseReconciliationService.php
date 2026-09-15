<?php

namespace App\Services\Operations;

use App\Models\CaseMaterialUsed;
use App\Models\CaseReconciliation;
use App\Models\SurgeryCase;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SurgeryCaseReconciliationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return array{status: string, rows: Collection<int, array<string, mixed>>, totals: array<string, int>, reconciliation: ?CaseReconciliation}
     */
    public function summary(SurgeryCase $case): array
    {
        $materials = $case->materialsUsed()
            ->with('inventoryLot.product')
            ->orderBy('id')
            ->get();

        return [
            'status' => $this->statusForMaterials($materials),
            'rows' => $materials->map(fn (CaseMaterialUsed $material): array => $this->row($material))->values(),
            'totals' => $this->totals($materials),
            'reconciliation' => $case->reconciliation,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reconcile(SurgeryCase $surgeryCase, array $data, int $userId): CaseReconciliation
    {
        return DB::transaction(function () use ($surgeryCase, $data, $userId): CaseReconciliation {
            $case = SurgeryCase::query()->lockForUpdate()->findOrFail($surgeryCase->id);
            $materials = CaseMaterialUsed::query()
                ->where('case_id', $case->id)
                ->lockForUpdate()
                ->get();

            if ($materials->isEmpty()) {
                throw new DomainException('La cirugia no tiene consumo registrado para conciliar.');
            }

            foreach ($materials as $material) {
                if ($material->difference_qty > 0 && blank($material->difference_reason)) {
                    throw new DomainException('No se puede conciliar una diferencia sin motivo registrado.');
                }
            }

            $totals = $this->totals($materials);
            $status = $this->statusForMaterials($materials);
            $before = $case->reconciliation?->only(['status', 'total_difference', 'observations']) ?? [];
            $reconciliation = CaseReconciliation::updateOrCreate(
                ['case_id' => $case->id],
                $totals + [
                    'status' => $status,
                    'observations' => $data['observations'] ?? null,
                    'completed_by' => $userId,
                    'completed_at' => now(),
                ],
            );

            $auditAction = $status === 'conciliado'
                ? 'reconciliation.completed'
                : 'reconciliation.observed';
            $this->auditLogger->record($auditAction, $reconciliation, $before, [
                'case_id' => $case->id,
                'status' => $status,
                'total_difference' => $totals['total_difference'],
                'total_failure' => $totals['total_failure'],
            ]);

            return $reconciliation;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function row(CaseMaterialUsed $material): array
    {
        $status = match (true) {
            $material->failure_qty > 0 => 'falla_reportada',
            $material->difference_qty > 0 => 'faltante',
            default => 'conciliado',
        };

        return [
            'product_code' => $material->inventoryLot?->product?->product_code,
            'product_name' => $material->inventoryLot?->product?->name,
            'lot' => $material->inventoryLot?->lot,
            'reserved_qty' => (int) $material->reserved_qty,
            'used_qty' => (int) $material->used_qty,
            'returned_qty' => (int) $material->returned_qty,
            'unused_opened_qty' => (int) $material->unused_opened_qty,
            'failure_qty' => (int) $material->failure_qty,
            'difference_qty' => (int) $material->difference_qty,
            'difference_reason' => $material->difference_reason,
            'status' => $status,
        ];
    }

    /**
     * @param  Collection<int, CaseMaterialUsed>  $materials
     * @return array<string, int>
     */
    private function totals(Collection $materials): array
    {
        return [
            'total_reserved' => (int) $materials->sum('reserved_qty'),
            'total_used' => (int) $materials->sum('used_qty'),
            'total_returned' => (int) $materials->sum('returned_qty'),
            'total_unused_opened' => (int) $materials->sum('unused_opened_qty'),
            'total_failure' => (int) $materials->sum('failure_qty'),
            'total_difference' => (int) $materials->sum('difference_qty'),
        ];
    }

    /**
     * @param  Collection<int, CaseMaterialUsed>  $materials
     */
    private function statusForMaterials(Collection $materials): string
    {
        if ($materials->contains(fn (CaseMaterialUsed $material): bool => $material->failure_qty > 0)) {
            return 'falla_reportada';
        }

        if ($materials->contains(fn (CaseMaterialUsed $material): bool => $material->difference_qty > 0)) {
            return 'faltante';
        }

        return 'conciliado';
    }
}
