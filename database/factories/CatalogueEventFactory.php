<?php

namespace Database\Factories;

use App\Models\CatalogueEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CatalogueEvent>
 */
class CatalogueEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $name = ucfirst(fake()->unique()->word()).' '.fake()->word(),
            'slug' => Str::slug($name),
            'starts_on' => fake()->dateTimeBetween('-2 years', 'now'),
            'ends_on' => null,
            'location' => fake()->city(),
            'description' => fake()->optional()->paragraph(),
        ];
    }
}
