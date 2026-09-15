<?php

namespace App\Services\Inventory;

use App\Enums\WarehouseType;
use App\Models\InventoryImportIssue;
use App\Models\InventoryLot;
use App\Models\KitRule;
use App\Models\Product;
use App\Models\SurgeryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReplenishmentForecastService
{
    public function __construct(private readonly StockEligibilityService $stockEligibility) {}

    /**
     * The first forecast version uses one unit per critical kit combination.
     * The service boundary leaves room for historical consumption later.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters = []): array
    {
        $minimum = (int) config('ops-biomed.min_surgeries', 3);
        $target = (int) config('ops-biomed.target_surgeries', 5);
        $rows = $this->criticalRows($minimum, $target);
        $filterOptions = $this->filterOptions($rows);
        $filteredRows = $this->applyFilters($rows, $filters);
        $atRiskRows = $filteredRows->whereIn('urgency', ['critical', 'high']);
        $affectedTypes = $atRiskRows
            ->flatMap(fn (array $row): array => $row['surgery_type_codes'])
            ->unique()
            ->values();

        return [
            'rows' => $filteredRows->values(),
            'filters' => $filters,
            'filter_options' => $filterOptions,
            'items_critical' => $filteredRows->where('urgency', 'critical')->count(),
            'items_high' => $filteredRows->where('urgency', 'high')->count(),
            'items_covered' => $filteredRows->where('urgency', 'normal')->count(),
            'under_target' => $filteredRows->where('urgency', '!=', 'normal')->count(),
            'suggested_purchase_total' => (int) $filteredRows->sum('suggested_purchase'),
            'affected_surgery_types' => $affectedTypes->count(),
            'ysan_support_available' => (int) $filteredRows->sum('stock_ysan'),
            'affected_type_summary' => $this->affectedTypeSummary($atRiskRows),
            'minimum' => $minimum,
            'target' => $target,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function criticalRows(int $minimum, int $target): Collection
    {
        $rules = SurgeryType::query()
            ->where('active', true)
            ->with(['kitRules' => fn ($query) => $query
                ->where('required', true)
                ->where('criticality', 'critica')
                ->orderBy('id')])
            ->orderBy('name')
            ->get()
            ->flatMap(fn (SurgeryType $type) => $type->kitRules);

        $stockByProduct = $this->stockByProduct();
        $productsByCombination = Product::query()
            ->where('active', true)
            ->get()
            ->groupBy(fn (Product $product): string => $this->combinationKey(
                $product->length_cm,
                $product->diameter_mm,
                $product->cut_type,
                $product->component_type,
            ));
        $inconsistenciesByProduct = $this->inconsistenciesByProduct();

        return $rules
            ->groupBy(fn (KitRule $rule): string => $this->combinationKey(
                $rule->length_cm,
                $rule->diameter_mm,
                $rule->cut_type,
                $rule->component_type,
            ))
            ->map(function (Collection $matchingRules, string $combinationKey) use (
                $minimum,
                $target,
                $stockByProduct,
                $productsByCombination,
                $inconsistenciesByProduct,
            ): Collection {
                /** @var KitRule $rule */
                $rule = $matchingRules->first();
                $products = $productsByCombination->get($combinationKey, collect());
                $surgeryTypes = $matchingRules
                    ->map(fn (KitRule $matchingRule): ?SurgeryType => $matchingRule->surgeryType)
                    ->filter()
                    ->unique('code')
                    ->values();

                if ($products->isEmpty()) {
                    return collect([$this->forecastRow(
                        null,
                        $rule,
                        $surgeryTypes,
                        $minimum,
                        $target,
                        $stockByProduct,
                        $inconsistenciesByProduct,
                    )]);
                }

                return $products->map(fn (Product $product): array => $this->forecastRow(
                    $product,
                    $rule,
                    $surgeryTypes,
                    $minimum,
                    $target,
                    $stockByProduct,
                    $inconsistenciesByProduct,
                ));
            })
            ->flatten(1)
            ->sortBy(fn (array $row): string => sprintf(
                '%d|%s|%s|%s',
                ['critical' => 0, 'high' => 1, 'normal' => 2][$row['urgency']],
                $row['surgery_type'],
                $this->dimensionLabel($row['length_cm']),
                $this->dimensionLabel($row['diameter_mm']),
            ))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function forecastRow(
        ?Product $product,
        KitRule $rule,
        Collection $surgeryTypes,
        int $minimum,
        int $target,
        Collection $stockByProduct,
        Collection $inconsistenciesByProduct,
    ): array {
        $stock = $stockByProduct->get($product?->getKey(), [
            'immediate_net' => 0,
            'ysan_net' => 0,
            'reserved_active' => 0,
        ]);
        $availableNet = (int) $stock['immediate_net'];
        $stockYsan = (int) $stock['ysan_net'];
        $urgency = $this->urgency($availableNet, $minimum, $target);
        $inconsistencyCount = (int) $inconsistenciesByProduct->get($product?->getKey(), 0);
        $productCode = trim((string) ($product?->product_code ?? ''));
        $hasProductCode = $product !== null && $productCode !== '';
        $observation = $hasProductCode
            ? ($inconsistencyCount > 0 ? 'Revisar inconsistencia de inventario.' : '')
            : 'SIN CÓDIGO — REVISAR EQUIVALENCIA';
        $productName = $hasProductCode
            ? (string) $product->name
            : ($product?->name ?: 'Producto no identificado');
        $productType = $product !== null
            ? $this->productTypeFromValues($product->cut_type, $product->component_type)
            : $this->productType($rule);

        return [
            'key' => $this->combinationKey($rule->length_cm, $rule->diameter_mm, $rule->cut_type, $rule->component_type).'|'.($product?->getKey() ?? 'unmatched'),
            'product_id' => $product?->getKey(),
            'surgery_type_codes' => $surgeryTypes->pluck('code')->all(),
            'surgery_types' => $surgeryTypes->pluck('name')->all(),
            'surgery_type' => $surgeryTypes->pluck('name')->implode(', '),
            'product_codes' => $hasProductCode ? [$productCode] : [],
            'product_code' => $hasProductCode ? $productCode : 'SIN CÓDIGO — REVISAR EQUIVALENCIA',
            'product_names' => $productName !== '' ? [$productName] : [],
            'product_name' => $productName,
            'family' => $product?->family ?: 'No registrada',
            'subfamily' => $product?->subfamily ?: 'No registrada',
            'length_cm' => $rule->length_cm,
            'diameter_mm' => $rule->diameter_mm,
            'product_type' => $productType,
            'cut_type' => $product?->cut_type ?: $rule->cut_type,
            'component_type' => $product?->component_type ?: $rule->component_type,
            'requirement' => $rule->notes ?: '1 unidad por cirugia como base inicial',
            'notes' => $rule->notes,
            'stock_immediate_net' => $availableNet,
            'stock_ysan' => $stockYsan,
            'reserved_active' => (int) $stock['reserved_active'],
            'minimum' => $minimum,
            'target' => $target,
            'deficit_minimum' => max(0, $minimum - $availableNet),
            'deficit_target' => max(0, $target - $availableNet),
            'suggested_purchase' => max(0, $target - $availableNet),
            'urgency' => $urgency,
            'urgency_label' => $this->urgencyLabel($urgency),
            'action' => $this->action($urgency, $stockYsan, $inconsistencyCount > 0),
            'observation' => $observation,
            'has_product_match' => $hasProductCode,
            'has_inconsistency' => $inconsistencyCount > 0,
            'inconsistency_count' => $inconsistencyCount,
        ];
    }

    /**
     * @return Collection<string, array{immediate_net: int, ysan_net: int, reserved_active: int}>
     */
    private function stockByProduct(): Collection
    {
        return $this->stockEligibility
            ->eligibleLotsQuery(true)
            ->withSum(['reservations as reserved_active' => fn (Builder $query) => $query->where('status', 'active')], 'quantity')
            ->with(['product', 'warehouse'])
            ->get()
            ->groupBy(fn (InventoryLot $lot): int => (int) $lot->product_id)
            ->map(function (Collection $lots): array {
                $immediateLots = $lots->reject(fn (InventoryLot $lot): bool => $this->isYsan($lot));
                $ysanLots = $lots->filter(fn (InventoryLot $lot): bool => $this->isYsan($lot));

                return [
                    'immediate_net' => $this->netQuantity($immediateLots),
                    'ysan_net' => $this->netQuantity($ysanLots),
                    'reserved_active' => (int) $lots->sum(fn (InventoryLot $lot): int => (int) ($lot->reserved_active ?? 0)),
                ];
            });
    }

    /**
     * @return Collection<string, int>
     */
    private function inconsistenciesByProduct(): Collection
    {
        return InventoryImportIssue::query()
            ->where('status', 'pending')
            ->whereHas('catalogImport', fn (Builder $query) => $query->where('status', 'committed'))
            ->with('product')
            ->get()
            ->filter(fn (InventoryImportIssue $issue): bool => $issue->product !== null)
            ->groupBy(fn (InventoryImportIssue $issue): int => (int) $issue->product_id)
            ->map(fn (Collection $issues): int => $issues->count());
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $rows, array $filters): Collection
    {
        return $rows
            ->filter(function (array $row) use ($filters): bool {
                if (($filters['surgery_type'] ?? null) && ! in_array($filters['surgery_type'], $row['surgery_type_codes'], true)) {
                    return false;
                }

                if (($filters['urgency'] ?? null) && $filters['urgency'] !== $row['urgency']) {
                    return false;
                }

                if (($filters['length'] ?? null) !== null && (float) $filters['length'] !== (float) $row['length_cm']) {
                    return false;
                }

                if (($filters['diameter'] ?? null) !== null && (float) $filters['diameter'] !== (float) $row['diameter_mm']) {
                    return false;
                }

                return ! (($filters['product_type'] ?? null) && $filters['product_type'] !== $row['product_type']);
            })
            ->sortBy(fn (array $row): string => sprintf(
                '%d|%s|%s|%s|%s',
                ['critical' => 0, 'high' => 1, 'normal' => 2][$row['urgency']],
                $row['surgery_type'],
                $this->dimensionLabel($row['length_cm']),
                $this->dimensionLabel($row['diameter_mm']),
                $row['product_type'],
            ))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function filterOptions(Collection $rows): array
    {
        $types = $rows
            ->flatMap(fn (array $row): array => array_combine($row['surgery_type_codes'], $row['surgery_types']) ?: [])
            ->unique()
            ->sort()
            ->map(fn (string $name, string $code): array => ['code' => $code, 'name' => $name])
            ->values()
            ->all();

        return [
            'surgery_types' => $types,
            'urgencies' => [
                ['value' => 'critical', 'label' => 'Critica'],
                ['value' => 'high', 'label' => 'Alta'],
                ['value' => 'normal', 'label' => 'Normal'],
            ],
            'lengths' => $rows->pluck('length_cm')->map(fn ($value): string => $this->dimensionLabel($value))->unique()->sort()->values()->all(),
            'diameters' => $rows->pluck('diameter_mm')->map(fn ($value): string => $this->dimensionLabel($value))->unique()->sort()->values()->all(),
            'product_types' => $rows->pluck('product_type')->unique()->sort()->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array{code: string, name: string, items: int}>
     */
    private function affectedTypeSummary(Collection $rows): array
    {
        return $rows
            ->flatMap(fn (array $row): array => collect($row['surgery_type_codes'])
                ->map(fn (string $code, int $index): array => [
                    'code' => $code,
                    'name' => $row['surgery_types'][$index] ?? $code,
                ])
                ->all())
            ->groupBy('code')
            ->map(fn (Collection $items): array => [
                'code' => $items->first()['code'],
                'name' => $items->first()['name'],
                'items' => $items->count(),
            ])
            ->sortByDesc('items')
            ->values()
            ->all();
    }

    private function urgency(int $availableNet, int $minimum, int $target): string
    {
        return $availableNet < $minimum ? 'critical' : ($availableNet < $target ? 'high' : 'normal');
    }

    private function urgencyLabel(string $urgency): string
    {
        return match ($urgency) {
            'critical' => 'Critica',
            'high' => 'Alta',
            default => 'Normal',
        };
    }

    private function action(string $urgency, int $stockYsan, bool $hasInconsistency): string
    {
        $action = match ($urgency) {
            'critical' => 'Comprar urgente. Bloquear promesa comercial.',
            'high' => 'Comprar para objetivo.',
            default => 'Sin accion.',
        };

        if ($stockYsan > 0 && $urgency !== 'normal') {
            $action .= ' Usar YSAN si la cirugia es mayor a 48 horas.';
        }

        if ($hasInconsistency) {
            $action .= ' Revisar inconsistencia.';
        }

        return $action;
    }

    private function netQuantity(Collection $lots): int
    {
        return (int) $lots->sum(fn (InventoryLot $lot): int => max(
            0,
            (int) $lot->quantity - (int) ($lot->reserved_active ?? 0),
        ));
    }

    private function isYsan(InventoryLot $lot): bool
    {
        return $lot->warehouse?->type === WarehouseType::Ysan;
    }

    private function productType(KitRule $rule): string
    {
        return $this->productTypeFromValues($rule->cut_type, $rule->component_type);
    }

    private function productTypeFromValues(?string $cutType, ?string $componentType): string
    {
        return match (true) {
            $componentType === 'telescopica' => 'telescopica',
            $cutType === 'iniciadora' => 'iniciadora',
            $componentType === 'cuchilla' => 'cuchilla',
            $cutType === 'cortante' => 'cortante',
            $cutType === 'diamantada' => 'diamantada',
            default => 'convencional',
        };
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

    private function dimensionLabel(mixed $value): string
    {
        if ($value === null) {
            return 'Cualquiera';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
