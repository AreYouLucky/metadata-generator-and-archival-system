<?php

namespace Database\Factories;

use App\Models\ExportSchema;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportSchema>
 */
class ExportSchemaFactory extends Factory
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
            'name' => fake()->words(2, true),
            'fields' => ['record.title', 'ai.description', 'ai.tags', 'transcript'],
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
