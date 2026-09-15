<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        $doctors = [
            ['name' => 'Dr. Carlos Ramirez', 'specialty' => 'Neurocirugia', 'institution' => 'Clinica Anglo Americana'],
            ['name' => 'Dra. Maria Torres', 'specialty' => 'Neurocirugia', 'institution' => 'Clinica Internacional'],
            ['name' => 'Dr. Luis Mendoza', 'specialty' => 'Cirugia de columna', 'institution' => 'Hospital Nacional Edgardo Rebagliati'],
        ];

        foreach ($doctors as $doctor) {
            $institutionId = Institution::query()
                ->where('name', $doctor['institution'])
                ->value('id');

            Doctor::updateOrCreate(
                ['name' => $doctor['name'], 'institution_id' => $institutionId],
                [
                    'specialty' => $doctor['specialty'],
                    'active' => true,
                ],
            );
        }
    }
}
