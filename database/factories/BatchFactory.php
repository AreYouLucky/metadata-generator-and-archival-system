<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
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
            'user_id' => User::factory()->encoder(),
            'name' => fake()->optional()->words(2, true),
            'status' => TaskStatus::Queued,
        ];
    }
}
