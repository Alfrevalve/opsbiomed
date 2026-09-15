<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = [
            ['name' => 'Clinica Anglo Americana', 'ruc' => '20100000001'],
            ['name' => 'Clinica Internacional', 'ruc' => '20100000002'],
            ['name' => 'Hospital Nacional Edgardo Rebagliati', 'ruc' => '20100000003'],
        ];

        foreach ($institutions as $institution) {
            Institution::updateOrCreate(
                ['name' => $institution['name']],
                $institution + ['active' => true],
            );
        }
    }
}
