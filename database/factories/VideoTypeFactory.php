<?php

namespace Database\Factories;

use App\Models\VideoType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VideoType>
 */
class VideoTypeFactory extends Factory
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
            'sort_order' => 0,
        ];
    }
}
