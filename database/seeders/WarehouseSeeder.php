<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            ['name' => 'ALMACEN PRINCIPAL', 'type' => 'principal', 'lead_time_hours' => 0, 'counts_as_immediate' => true],
            ['name' => 'ALMACEN CONSIGNACION', 'type' => 'consignacion', 'lead_time_hours' => 0, 'counts_as_immediate' => true],
            ['name' => 'ALMACEN YSAN', 'type' => 'ysan', 'lead_time_hours' => 48, 'counts_as_immediate' => false],
            ['name' => 'ALMACEN DESVALORIZADO', 'type' => 'desvalorizado', 'lead_time_hours' => 0, 'counts_as_immediate' => false],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(['name' => $warehouse['name']], $warehouse + ['active' => true]);
        }
    }
}
