<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectSeries>
 */
class ProjectSeriesFactory extends Factory
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
            'title' => fake()->sentence(3),
            'date' => fake()->dateTimeBetween('-2 years', 'now'),
            'description' => fake()->optional()->paragraph(),
        ];
    }
}
