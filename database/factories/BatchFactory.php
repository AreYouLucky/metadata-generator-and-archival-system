<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->encoder(),
            'name' => fake()->optional()->sentence(3),
            'status' => BatchStatus::Processing,
            'total' => 0,
            'completed' => 0,
            'failed' => 0,
        ];
    }
}
