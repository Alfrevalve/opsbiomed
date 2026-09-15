<?php

namespace Database\Factories;

use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'module' => fake()->randomElement(['inventory', 'operations', 'reports']),
            'action' => 'operational_suggestion',
            'input_type' => 'aggregated_operational_data',
            'contains_personal_data' => false,
            'contains_health_data' => false,
            'ai_provider' => null,
            'human_review_required' => true,
            'human_reviewed_by' => null,
            'human_reviewed_at' => null,
            'human_review_outcome' => null,
            'created_at' => now(),
        ];
    }
}
