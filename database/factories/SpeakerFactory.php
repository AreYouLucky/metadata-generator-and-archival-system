<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Speaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Speaker>
 */
class SpeakerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'label' => 'S'.fake()->unique()->numberBetween(1, 99),
            'display_name' => fake()->optional()->name(),
        ];
    }
}
