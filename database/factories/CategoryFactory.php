<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
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
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }
}
