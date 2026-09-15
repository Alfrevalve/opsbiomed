<?php

namespace Database\Seeders;

use App\Models\SurgeryType;
use Illuminate\Database\Seeder;

class SurgeryTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'cervical', 'name' => 'Cervical'],
            ['code' => 'craneo', 'name' => 'Craneo'],
            ['code' => 'endoscopica_no_nasal', 'name' => 'Endoscopica no nasal'],
            ['code' => 'tubular', 'name' => 'Tubular'],
            ['code' => 'endoscopica_nasal', 'name' => 'Endoscopica nasal'],
            ['code' => 'toracica_lumbar', 'name' => 'Columna toracica lumbar'],
        ];

        foreach ($types as $type) {
            SurgeryType::updateOrCreate(['code' => $type['code']], $type + ['active' => true]);
        }
    }
}
