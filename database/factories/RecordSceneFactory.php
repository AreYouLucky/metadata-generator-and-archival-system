<?php

namespace Database\Factories;

use App\Enums\SceneKind;
use App\Models\Record;
use App\Models\RecordScene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordScene>
 */
class RecordSceneFactory extends Factory
{
    /**
     * Define the model's default state: a detected shot.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->randomFloat(3, 0, 500);

        return [
            'record_id' => Record::factory(),
            'kind' => SceneKind::Shot,
            'start' => $start,
            'end' => $start + fake()->randomFloat(3, 1, 30),
            'tc_in' => null,
            'tc_out' => null,
            'label' => null,
            'description' => null,
        ];
    }

    /**
     * A named group of shots made by an encoder.
     */
    public function scene(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => SceneKind::Scene,
            'label' => fake()->words(3, true),
        ]);
    }
}
