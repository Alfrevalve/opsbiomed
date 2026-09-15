<?php

namespace Database\Seeders;

use App\Enums\InventoryStatus;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class ProductInventorySeeder extends Seeder
{
    public function run(): void
    {
        $principal = Warehouse::query()->where('name', 'ALMACEN PRINCIPAL')->firstOrFail();
        $ysan = Warehouse::query()->where('name', 'ALMACEN YSAN')->firstOrFail();
        $desvalorizado = Warehouse::query()->where('name', 'ALMACEN DESVALORIZADO')->firstOrFail();

        $products = [
            ['code' => 'MR8-CERV-09-01-C', 'name' => 'Fresa MR8 cervical 9 cm 1 mm cortante', 'diameter' => 1, 'quantity' => 6],
            ['code' => 'MR8-CERV-09-01-D', 'name' => 'Fresa MR8 cervical 9 cm 1 mm diamantada', 'diameter' => 1, 'quantity' => 12, 'cut_type' => 'diamantada'],
            ['code' => 'MR8-CERV-09-02-C', 'name' => 'Fresa MR8 cervical 9 cm 2 mm cortante', 'diameter' => 2, 'quantity' => 15],
        ];

        foreach ($products as $definition) {
            $cutType = $definition['cut_type'] ?? 'cortante';
            $product = Product::updateOrCreate(
                ['product_code' => $definition['code']],
                [
                    'product_line' => 'MR8',
                    'family' => 'Fresas',
                    'subfamily' => 'Cervical',
                    'normalized_code' => $definition['code'],
                    'name' => $definition['name'],
                    'length_cm' => 9,
                    'diameter_mm' => $definition['diameter'],
                    'cut_type' => $cutType,
                    'component_type' => 'fresa',
                    'tracking_type' => 'lot',
                    'active' => true,
                ],
            );

            InventoryLot::updateOrCreate(
                ['product_id' => $product->id, 'lot' => 'LOTE-INICIAL-'.$definition['code']],
                [
                    'serial' => null,
                    'expiry' => now()->addYears(2)->toDateString(),
                    'warehouse_id' => $principal->id,
                    'quantity' => $definition['quantity'],
                    'status' => InventoryStatus::Apto,
                    'eligible_flag' => true,
                ],
            );
        }

        $ysanProduct = Product::query()->where('product_code', 'MR8-CERV-09-01-C')->firstOrFail();
        InventoryLot::updateOrCreate(
            ['product_id' => $ysanProduct->id, 'lot' => 'LOTE-YSAN-INICIAL'],
            [
                'expiry' => now()->addYears(2)->toDateString(),
                'warehouse_id' => $ysan->id,
                'quantity' => 20,
                'status' => InventoryStatus::Apto,
                'eligible_flag' => true,
            ],
        );

        $desvalorizadoProduct = Product::query()->where('product_code', 'MR8-CERV-09-01-D')->firstOrFail();
        InventoryLot::updateOrCreate(
            ['product_id' => $desvalorizadoProduct->id, 'lot' => 'LOTE-DESVALORIZADO-INICIAL'],
            [
                'expiry' => now()->addYears(2)->toDateString(),
                'warehouse_id' => $desvalorizado->id,
                'quantity' => 20,
                'status' => InventoryStatus::Apto,
                'eligible_flag' => true,
            ],
        );
    }
}
