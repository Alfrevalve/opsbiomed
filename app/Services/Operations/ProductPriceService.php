<?php

namespace App\Services\Operations;

use App\Models\ProductPrice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ProductPriceService
{
    public function current(
        int $productId,
        ?int $institutionId = null,
        ?int $doctorId = null,
        ?CarbonInterface $onDate = null,
    ): ?ProductPrice {
        $date = $onDate ?? today();

        return ProductPrice::query()
            ->where('product_id', $productId)
            ->where('active', true)
            ->where(function (Builder $query) use ($institutionId): void {
                if ($institutionId === null) {
                    $query->whereNull('institution_id');

                    return;
                }

                $query->whereNull('institution_id')->orWhere('institution_id', $institutionId);
            })
            ->where(function (Builder $query) use ($doctorId): void {
                if ($doctorId === null) {
                    $query->whereNull('doctor_id');

                    return;
                }

                $query->whereNull('doctor_id')->orWhere('doctor_id', $doctorId);
            })
            ->where(fn (Builder $query): Builder => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn (Builder $query): Builder => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date))
            ->orderByRaw('(CASE WHEN doctor_id IS NOT NULL THEN 2 ELSE 0 END) + (CASE WHEN institution_id IS NOT NULL THEN 1 ELSE 0 END) DESC')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{unit_price: float, minimum_price: ?float, price_below_minimum: bool, price: ?ProductPrice, manual: bool}
     */
    public function resolveLine(
        array $line,
        int $productId,
        ?int $institutionId,
        ?int $doctorId,
        int $usedQuantity,
        bool $costZero,
        User $user,
    ): array {
        $manual = array_key_exists('unit_price', $line) && $line['unit_price'] !== null && $line['unit_price'] !== '';

        if ($manual && ! $this->canManuallyOverride($user)) {
            throw new \DomainException('No tienes permiso para registrar un precio manual en el cierre.');
        }

        if ($costZero) {
            if (blank($line['cost_zero_reason'] ?? null) && $usedQuantity > 0) {
                throw new \DomainException('El costo cero requiere un motivo.');
            }

            return [
                'unit_price' => 0.0,
                'minimum_price' => null,
                'price_below_minimum' => false,
                'price' => null,
                'manual' => $manual,
            ];
        }

        $price = $this->current($productId, $institutionId, $doctorId);
        $unitPrice = $manual
            ? (float) $line['unit_price']
            : (float) ($price?->unit_price ?? 0);

        if ($usedQuantity > 0 && ! $manual && $price === null) {
            throw new \DomainException('El material usado requiere precio unitario o costo cero con motivo.');
        }

        $minimumPrice = $price?->minimum_price !== null ? (float) $price->minimum_price : null;

        return [
            'unit_price' => $unitPrice,
            'minimum_price' => $minimumPrice,
            'price_below_minimum' => $manual && $minimumPrice !== null && $unitPrice < $minimumPrice,
            'price' => $price,
            'manual' => $manual,
        ];
    }

    public function canManuallyOverride(User $user): bool
    {
        return $user->can('prices.manage') || $user->can('cases.close');
    }
}
