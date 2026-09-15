<?php

namespace Database\Factories;

use App\Models\CaseResourceAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseResourceAssignment>
 */
class CaseResourceAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_lot_id' => null,
            'resource_type' => fake()->randomElement([
                'equipo',
                'motor',
                'consola',
                'pedal',
                'acople',
                'set',
            ]),
            'assigned_by' => null,
            'assigned_at' => now(),
        ];
    }
}
