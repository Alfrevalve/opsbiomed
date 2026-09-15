<?php

namespace App\Services\Inventory;

use App\Models\InventoryImportIssue;
use App\Models\InventoryLot;
use App\Models\KitRule;
use App\Models\SurgeryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SurgeryCoverageService
{
    public function __construct(private readonly StockEligibilityService $stockEligibility) {}

    /**
     * @return array{
     *     types: Collection<int, array<string, mixed>>,
     *     rows: Collection<int, array<string, mixed>>,
     *     red: int,
     *     yellow: int,
     *     complete_types: int,
     *     at_risk_types: int,
     *     critical_under_minimum: int,
     *     under_target: int
     * }
     */
    public function summary(): array
    {
        $types = SurgeryType::query()
            ->where('active', true)
            ->with(['kitRules' => fn ($query) => $query->where('required', true)->orderBy('id')])
            ->orderBy('name')
            ->get();
        $stockByCombination = $this->stockByCombination();
        $inconsistenciesByCombination = $this->inconsistenciesByCombination();
        $rows = collect();

        $typeSummaries = $types->map(function (SurgeryType $type) use ($stockByCombination, $inconsistenciesByCombination, &$rows): array {
            $typeRows = $type->kitRules->map(function (KitRule $rule) use ($stockByCombination, $inconsistenciesByCombination, &$rows, $type): array {
                $combinationKey = $this->combinationKey($rule->length_cm, $rule->diameter_mm, $rule->cut_type, $rule->component_type);
                $stock = $stockByCombination->get($combinationKey, [
                    'stock_elegible' => 0,
                    'reserved_active' => 0,
                    'available_net' => 0,
                ]);
                $minimum = (int) config('ops-biomed.min_surgeries', 3);
                $target = (int) config('ops-biomed.target_surgeries', 5);
                $risk = $this->riskForNet((int) $stock['available_net'], $minimum, $target);
                $hasInconsistency = (int) $inconsistenciesByCombination->get($combinationKey, 0) > 0;
                $row = [
                    'surgery_type_code' => $type->code,
                    'surgery_type' => $type->name,
                    'key' => $combinationKey,
                    'label' => $this->combinationLabel($rule),
                    'length_cm' => $rule->length_cm,
                    'diameter_mm' => $rule->diameter_mm,
                    'cut_type' => $rule->cut_type,
                    'component_type' => $rule->component_type,
                    'requirement' => $rule->criticality,
                    'criticality' => $rule->criticality,
                    'notes' => $rule->notes,
                    'stock_elegible' => (int) $stock['stock_elegible'],
                    'reserved_active' => (int) $stock['reserved_active'],
                    'available_net' => (int) $stock['available_net'],
                    'minimum' => $minimum,
                    'target' => $target,
                    'risk' => $risk,
                    'risk_label' => $this->riskLabel($risk),
                    'coverage' => (int) $stock['available_net'],
                    'has_inconsistency' => $hasInconsistency,
                    'recommendation' => $this->recommendation($risk, $hasInconsistency),
                ];
                $rows->push($row);

                return $row;
            })->values();

            return [
                'code' => $type->code,
                'name' => $type->name,
                'rows' => $typeRows,
                'red' => $typeRows->where('risk', 'red')->count(),
                'yellow' => $typeRows->where('risk', 'yellow')->count(),
                'green' => $typeRows->where('risk', 'green')->count(),
                'complete' => $typeRows->isNotEmpty() && $typeRows->every(fn (array $row): bool => $row['risk'] === 'green'),
                'at_risk' => $typeRows->contains(fn (array $row): bool => in_array($row['risk'], ['red', 'yellow'], true)),
            ];
        })->values();

        return [
            'types' => $typeSummaries,
            'rows' => $rows,
            'red' => $rows->where('risk', 'red')->count(),
            'yellow' => $rows->where('risk', 'yellow')->count(),
            'complete_types' => $typeSummaries->where('complete', true)->count(),
            'at_risk_types' => $typeSummaries->where('at_risk', true)->count(),
            'critical_under_minimum' => $rows
                ->where('criticality', 'critica')
                ->where('available_net', '<', (int) config('ops-biomed.min_surgeries', 3))
                ->count(),
            'under_target' => $rows
                ->where('available_net', '<', (int) config('ops-biomed.target_surgeries', 5))
                ->count(),
        ];
    }

    /**
     * @return Collection<string, array{stock_elegible: int, reserved_active: int, available_net: int}>
     */
    private function stockByCombination(): Collection
    {
        return $this->stockEligibility->eligibleLotsQuery()
            ->withSum(['reservations as reserved_active' => fn (Builder $query) => $query->where('status', 'active')], 'quantity')
            ->with('product')
            ->get()
            ->groupBy(fn (InventoryLot $lot): string => $this->combinationKey(
                $lot->product?->length_cm,
                $lot->product?->diameter_mm,
                $lot->product?->cut_type,
                $lot->product?->component_type,
            ))
            ->map(fn (Collection $lots): array => [
                'stock_elegible' => (int) $lots->sum('quantity'),
                'reserved_active' => (int) $lots->sum(fn (InventoryLot $lot): int => (int) ($lot->reserved_active ?? 0)),
                'available_net' => (int) $lots->sum(fn (InventoryLot $lot): int => max(0, (int) $lot->quantity - (int) ($lot->reserved_active ?? 0))),
            ]);
    }

    /**
     * @return Collection<string, int>
     */
    private function inconsistenciesByCombination(): Collection
    {
        return InventoryImportIssue::query()
            ->where('status', 'pending')
            ->whereHas('catalogImport', fn (Builder $query) => $query->where('status', 'committed'))
            ->with('product')
            ->get()
            ->filter(fn (InventoryImportIssue $issue): bool => $issue->product !== null)
            ->groupBy(fn (InventoryImportIssue $issue): string => $this->combinationKey(
                $issue->product?->length_cm,
                $issue->product?->diameter_mm,
                $issue->product?->cut_type,
                $issue->product?->component_type,
            ))
            ->map(fn (Collection $issues): int => $issues->count());
    }

    private function riskForNet(int $availableNet, int $minimum, int $target): string
    {
        return $availableNet >= $target ? 'green' : ($availableNet >= $minimum ? 'yellow' : 'red');
    }

    private function riskLabel(string $risk): string
    {
        return match ($risk) {
            'green' => 'Verde',
            'yellow' => 'Amarillo',
            default => 'Rojo',
        };
    }

    private function recommendation(string $risk, bool $hasInconsistency): string
    {
        $recommendation = match ($risk) {
            'green' => 'Cobertura completa. Mantener stock inmediato.',
            'yellow' => 'Comprar o mover stock inmediato. Considerar YSAN si la cirugia es mayor a 48 horas.',
            default => 'Comprar y mover stock inmediato si existe. Bloquear promesa comercial; considerar YSAN solo si la cirugia es mayor a 48 horas.',
        };

        if ($hasInconsistency) {
            $recommendation .= ' Revisar la inconsistencia de inventario asociada.';
        }

        return $recommendation;
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

    private function combinationLabel(KitRule $rule): string
    {
        return sprintf(
            '%s cm / %s mm / %s / %s',
            $rule->length_cm ?? 'cualquier',
            $rule->diameter_mm ?? 'cualquier',
            $rule->cut_type,
            $rule->component_type,
        );
    }
}
