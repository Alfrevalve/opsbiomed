<?php

namespace Database\Seeders;

use App\Models\KitRule;
use App\Models\SurgeryType;
use Illuminate\Database\Seeder;

class KitRuleSeeder extends Seeder
{
    public function run(): void
    {
        $min = config('ops-biomed.min_surgeries', 3);
        $target = config('ops-biomed.target_surgeries', 5);

        $rules = [
            'cervical' => [
                [9, 1, 'cortante', 'fresa', 'critica', 'Mas prioridad a 2 mm que a 1 mm.'],
                [9, 1, 'diamantada', 'fresa', 'critica', 'Mas prioridad a 2 mm que a 1 mm.'],
                [9, 2, 'cortante', 'fresa', 'critica', 'Mayor consumo esperado.'],
                [9, 2, 'diamantada', 'fresa', 'critica', 'Mayor consumo esperado.'],
                [9, 3, 'cortante', 'fresa', 'critica', null],
                [9, 3, 'diamantada', 'fresa', 'critica', null],
                [10, 1, 'cortante', 'fresa', 'critica', null],
                [10, 1, 'diamantada', 'fresa', 'critica', null],
                [10, 2, 'cortante', 'fresa', 'critica', 'Mayor consumo esperado.'],
                [10, 2, 'diamantada', 'fresa', 'critica', 'Mayor consumo esperado.'],
                [10, 3, 'cortante', 'fresa', 'critica', null],
                [10, 3, 'diamantada', 'fresa', 'critica', null],
            ],
            'craneo' => [
                [7, 3, 'cortante', 'fresa', 'critica', null],
                [7, 3, 'diamantada', 'fresa', 'critica', null],
                [9, 3, 'cortante', 'fresa', 'critica', null],
                [9, 3, 'diamantada', 'fresa', 'critica', null],
                [10, 3, 'cortante', 'fresa', 'critica', null],
                [10, 3, 'diamantada', 'fresa', 'critica', null],
                [7, 4, 'cortante', 'fresa', 'critica', null],
                [7, 4, 'diamantada', 'fresa', 'critica', null],
                [9, 4, 'cortante', 'fresa', 'critica', null],
                [9, 4, 'diamantada', 'fresa', 'critica', null],
                [10, 4, 'cortante', 'fresa', 'critica', null],
                [10, 4, 'diamantada', 'fresa', 'critica', null],
                [7, 5, 'cortante', 'fresa', 'critica', null],
                [7, 5, 'diamantada', 'fresa', 'critica', null],
                [9, 5, 'cortante', 'fresa', 'critica', null],
                [9, 5, 'diamantada', 'fresa', 'critica', null],
                [10, 5, 'cortante', 'fresa', 'critica', null],
                [10, 5, 'diamantada', 'fresa', 'critica', null],
                [null, null, 'iniciadora', 'cuchilla', 'critica', 'Iniciadora obligatoria.'],
                [null, null, 'F2', 'cuchilla', 'segun_caso', 'Cuchilla F2 segun caso.'],
                [null, null, 'F3', 'cuchilla', 'segun_caso', 'Cuchilla F3 segun caso.'],
            ],
            'endoscopica_no_nasal' => [
                [14, 2.2, 'cortante', 'fresa', 'critica', 'No usar 4 ni 5 mm como base.'],
                [14, 2.2, 'diamantada', 'fresa', 'critica', 'No usar 4 ni 5 mm como base.'],
                [14, 3, 'cortante', 'fresa', 'critica', null],
                [14, 3, 'diamantada', 'fresa', 'critica', null],
            ],
            'tubular' => [
                [12, 3, 'cortante', 'telescopica', 'critica', null],
                [12, 3, 'diamantada', 'telescopica', 'critica', null],
                [14, 3, 'cortante', 'telescopica', 'critica', null],
                [14, 3, 'diamantada', 'telescopica', 'critica', null],
                [14, 3, 'cortante', 'fresa', 'backup', 'Convencional backup.'],
                [14, 3, 'diamantada', 'fresa', 'backup', 'Convencional backup.'],
                [14, 4, 'cortante', 'fresa', 'backup', 'Convencional backup.'],
                [14, 4, 'diamantada', 'fresa', 'backup', 'Convencional backup.'],
                [14, 5, 'cortante', 'fresa', 'backup', 'Convencional backup.'],
                [14, 5, 'diamantada', 'fresa', 'backup', 'Convencional backup.'],
            ],
            'endoscopica_nasal' => [
                [12, 3, 'cortante', 'telescopica', 'critica', null],
                [12, 3, 'diamantada', 'telescopica', 'critica', null],
                [14, 3, 'cortante', 'telescopica', 'critica', null],
                [14, 3, 'diamantada', 'telescopica', 'critica', null],
                [7, 3, 'cortante', 'fresa', 'backup', 'Backup 7 cm.'],
                [9, 3, 'cortante', 'fresa', 'backup', 'Backup 9 cm.'],
                [14, 3, 'cortante', 'fresa', 'backup', 'Convencional backup.'],
                [14, 4, 'cortante', 'fresa', 'backup', 'Convencional backup.'],
                [14, 5, 'cortante', 'fresa', 'backup', 'Convencional backup.'],
                [null, null, 'iniciadora', 'cuchilla', 'critica', 'Agregar iniciadora.'],
                [null, null, 'F2', 'cuchilla', 'segun_caso', 'Agregar cuchilla F2 segun caso.'],
                [null, null, 'F3', 'cuchilla', 'segun_caso', 'Agregar cuchilla F3 segun caso.'],
            ],
            'toracica_lumbar' => [
                [9, 3, 'cortante', 'fresa', 'critica', null],
                [9, 3, 'diamantada', 'fresa', 'critica', null],
                [9, 4, 'cortante', 'fresa', 'critica', null],
                [9, 4, 'diamantada', 'fresa', 'critica', null],
                [9, 5, 'cortante', 'fresa', 'critica', null],
                [9, 5, 'diamantada', 'fresa', 'critica', null],
                [10, 3, 'cortante', 'fresa', 'critica', null],
                [10, 3, 'diamantada', 'fresa', 'critica', null],
                [10, 4, 'cortante', 'fresa', 'critica', null],
                [10, 4, 'diamantada', 'fresa', 'critica', null],
                [10, 5, 'cortante', 'fresa', 'critica', null],
                [10, 5, 'diamantada', 'fresa', 'critica', null],
                [14, 3, 'cortante', 'fresa', 'critica', null],
                [14, 3, 'diamantada', 'fresa', 'critica', null],
                [14, 4, 'cortante', 'fresa', 'critica', null],
                [14, 4, 'diamantada', 'fresa', 'critica', null],
                [14, 5, 'cortante', 'fresa', 'critica', null],
                [14, 5, 'diamantada', 'fresa', 'critica', null],
                [null, null, 'iniciadora', 'cuchilla', 'segun_caso', 'Preestablecido segun caso.'],
                [null, null, 'F1', 'cuchilla', 'segun_caso', 'Cuchilla F1 segun caso.'],
                [null, null, 'F2', 'cuchilla', 'segun_caso', 'Cuchilla F2 segun caso.'],
                [null, null, 'F3', 'cuchilla', 'segun_caso', 'Cuchilla F3 segun caso.'],
            ],
        ];

        foreach ($rules as $surgeryTypeCode => $typeRules) {
            $surgeryType = SurgeryType::where('code', $surgeryTypeCode)->firstOrFail();

            foreach ($typeRules as [$length, $diameter, $cutType, $componentType, $criticality, $notes]) {
                KitRule::updateOrCreate([
                    'surgery_type_id' => $surgeryType->id,
                    'length_cm' => $length,
                    'diameter_mm' => $diameter,
                    'cut_type' => $cutType,
                    'component_type' => $componentType,
                ], [
                    'min_qty' => $min,
                    'target_qty' => $target,
                    'criticality' => $criticality,
                    'required' => true,
                    'notes' => $notes,
                ]);
            }
        }
    }
}
