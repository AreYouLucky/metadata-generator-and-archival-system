<?php

namespace Database\Factories;

use App\Enums\MetadataSource;
use App\Enums\SubjectType;
use App\Models\Project;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
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
            'name' => fake()->name(),
            'type' => SubjectType::Person,
            'source' => MetadataSource::Manual,
            'is_confirmed' => true,
        ];
    }

    /**
     * An unconfirmed AI suggestion.
     */
    public function suggested(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => MetadataSource::Ai,
            'is_confirmed' => false,
        ]);
    }
}
