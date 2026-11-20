<?php

namespace Database\Factories;

use App\Models\ProductionAgency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionAgency>
 */
class ProductionAgencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->company(),
            'acronym' => strtoupper(fake()->lexify('????')),
        ];
    }

    /**
     * A production unit (division) under the given agency.
     */
    public function unitOf(ProductionAgency $agency): static
    {
        return $this->state(fn (array $attributes) => ['parent_id' => $agency->id]);
    }
}
