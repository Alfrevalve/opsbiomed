<?php

namespace Database\Factories;

use App\Models\TraceEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TraceEvent>
 */
class TraceEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trace_code' => 'LOT-'.fake()->unique()->numberBetween(1, 999999).'-MR8-TEST',
            'event_type' => 'scanned',
            'context' => ['source' => 'factory'],
            'created_at' => now(),
        ];
    }
}
