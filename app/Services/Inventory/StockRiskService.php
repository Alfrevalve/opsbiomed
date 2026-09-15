<?php

namespace App\Services\Inventory;

use App\Enums\CaseStatus;
use App\Models\InventoryLot;
use App\Models\KitRule;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use Illuminate\Support\Collection;

class StockRiskService
{
    public function __construct(private readonly StockEligibilityService $stockEligibility) {}

    /**
     * Return stock coverage for one exact product combination.
     * Coverage is measured in complete surgeries, not individual units.
     *
     * @return array<string, mixed>
     */
    public function riskForRule(KitRule $rule): array
    {
        $lots = $this->stockEligibility->queryForRule($rule)
            ->with(['product', 'warehouse'])
            ->get();
        $stockTotal = (int) $lots->sum('quantity');
        $reservedActive = (int) $lots->sum(fn (InventoryLot $lot): int => $this->stockEligibility->activeReservedQuantity($lot));
        $availableNet = (int) $lots->sum(fn (InventoryLot $lot): int => $this->stockEligibility->netAvailableForLot($lot));
        $unitsPerSurgery = max(1, (int) $rule->min_qty);
        $coverage = intdiv($availableNet, $unitsPerSurgery);
        $minimum = (int) config('ops-biomed.min_surgeries', 3);
        $target = (int) config('ops-biomed.target_surgeries', 5);
        $risk = $coverage >= $target ? 'green' : ($coverage >= $minimum ? 'yellow' : 'red');

        return [
            'key' => $this->combinationKey($rule),
            'label' => $this->combinationLabel($rule),
            'length_cm' => $rule->length_cm,
            'diameter_mm' => $rule->diameter_mm,
            'cut_type' => $rule->cut_type,
            'component_type' => $rule->component_type,
            'stock_total' => $stockTotal,
            'reserved_active' => $reservedActive,
            'available_net' => $availableNet,
            'units_per_surgery' => $unitsPerSurgery,
            'coverage' => $coverage,
            'minimum_surgeries' => $minimum,
            'target_surgeries' => $target,
            'risk' => $risk,
            'risk_label' => match ($risk) {
                'green' => 'Verde',
                'yellow' => 'Amarillo',
                default => 'Rojo',
            },
            'rule' => $rule,
        ];
    }

    /**
     * @return array{risks: Collection<int, array<string, mixed>>, lots: Collection<int, InventoryLot>, complete: bool}
     */
    public function caseOverview(SurgeryCase $case): array
    {
        $case->loadMissing([
            'surgeryType.kitRules',
            'reservations.reservedBy',
            'reservations.inventoryLot.product',
            'reservations.inventoryLot.warehouse',
        ]);

        $rules = $case->surgeryType?->kitRules?->where('required', true) ?? collect();
        $risks = $rules->map(function (KitRule $rule) use ($case): array {
            $risk = $this->riskForRule($rule);
            $risk['reserved_for_case'] = $this->reservedForCase($case, $rule);
            $risk['pending_for_case'] = max(0, (int) $risk['units_per_surgery'] - $risk['reserved_for_case']);

            return $risk;
        })->values();

        $lots = collect();
        foreach ($rules as $rule) {
            $risk = $risks->firstWhere('key', $this->combinationKey($rule));

            foreach ($this->stockEligibility->queryForRule($rule)->with(['product', 'warehouse'])->get() as $lot) {
                $lot->setAttribute('stock_total', (int) $lot->quantity);
                $lot->setAttribute('reserved_active', $this->stockEligibility->activeReservedQuantity($lot));
                $lot->setAttribute('available_net', $this->stockEligibility->netAvailableForLot($lot));
                $lot->setAttribute('risk', $risk['risk'] ?? 'red');
                $lots->push($lot);
            }
        }

        return [
            'risks' => $risks,
            'lots' => $lots->unique('id')->values(),
            'complete' => $risks->every(fn (array $risk): bool => $risk['pending_for_case'] === 0),
        ];
    }

    /**
     * @return array{risks: Collection<int, array<string, mixed>>, red: int, yellow: int, active_reservations: int, incomplete_cases: Collection<int, SurgeryCase>}
     */
    public function dashboardSummary(): array
    {
        $risks = KitRule::query()
            ->where('required', true)
            ->get()
            ->groupBy(fn (KitRule $rule): string => $this->combinationKey($rule))
            ->map(fn (Collection $rules): array => $this->riskForRule($rules->first()))
            ->values();

        $incompleteCases = $this->incompleteReservationCases();

        return [
            'risks' => $risks,
            'red' => $risks->where('risk', 'red')->count(),
            'yellow' => $risks->where('risk', 'yellow')->count(),
            'active_reservations' => Reservation::query()->where('status', 'active')->count(),
            'incomplete_cases' => $incompleteCases,
        ];
    }

    /**
     * @return Collection<int, SurgeryCase>
     */
    public function incompleteReservationCases(): Collection
    {
        $closedStatuses = [
            CaseStatus::Cerrado->value,
            CaseStatus::Cerrada->value,
            CaseStatus::Facturacion->value,
            CaseStatus::Facturada->value,
            CaseStatus::Cancelado->value,
        ];

        return SurgeryCase::query()
            ->with(['surgeryType.kitRules', 'reservations.inventoryLot.product'])
            ->whereNotIn('status', $closedStatuses)
            ->get()
            ->filter(function (SurgeryCase $case): bool {
                $rules = $case->surgeryType?->kitRules?->where('required', true) ?? collect();

                if ($rules->isEmpty()) {
                    return false;
                }

                return $rules->contains(fn (KitRule $rule): bool => $this->reservedForCase($case, $rule) < (int) $rule->min_qty);
            })
            ->values();
    }

    private function reservedForCase(SurgeryCase $case, KitRule $rule): int
    {
        return (int) $case->reservations
            ->where('status', 'active')
            ->filter(function (Reservation $reservation) use ($rule): bool {
                $product = $reservation->inventoryLot?->product;

                return $product
                    && (string) $product->length_cm === (string) $rule->length_cm
                    && (string) $product->diameter_mm === (string) $rule->diameter_mm
                    && $product->cut_type === $rule->cut_type
                    && $product->component_type === $rule->component_type;
            })
            ->sum('quantity');
    }

    private function combinationKey(KitRule $rule): string
    {
        return implode('|', [
            number_format((float) $rule->length_cm, 2, '.', ''),
            number_format((float) $rule->diameter_mm, 2, '.', ''),
            $rule->cut_type,
            $rule->component_type,
        ]);
    }

    private function combinationLabel(KitRule $rule): string
    {
        return sprintf(
            '%s cm / %s mm / %s / %s',
            $rule->length_cm,
            $rule->diameter_mm,
            $rule->cut_type,
            $rule->component_type,
        );
    }
}
