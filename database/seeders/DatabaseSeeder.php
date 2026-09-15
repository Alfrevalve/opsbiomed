<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            OperationalSlaSeeder::class,
            InitialUserSeeder::class,
            InstitutionSeeder::class,
            DoctorSeeder::class,
            WarehouseSeeder::class,
            ProductInventorySeeder::class,
            SurgeryTypeSeeder::class,
            KitRuleSeeder::class,
        ]);
    }
}
