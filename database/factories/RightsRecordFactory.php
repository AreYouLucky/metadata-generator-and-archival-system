<?php

namespace Database\Factories;

use App\Enums\Licence;
use App\Models\Project;
use App\Models\RightsRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RightsRecord>
 */
class RightsRecordFactory extends Factory
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
            'copyright_holder' => fake()->company(),
            'copyright_year' => (int) fake()->year(),
            'licence' => Licence::AllRightsReserved,
            'embargo_until' => null,
        ];
    }

    public function embargoed(): static
    {
        return $this->state(fn (array $attributes) => [
            'embargo_until' => now()->addMonth(),
        ]);
    }
}
