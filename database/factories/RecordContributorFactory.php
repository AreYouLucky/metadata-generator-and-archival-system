<?php

namespace Database\Factories;

use App\Enums\ContributorRole;
use App\Models\Record;
use App\Models\RecordContributor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordContributor>
 */
class RecordContributorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'record_id' => Record::factory(),
            'role' => fake()->randomElement(ContributorRole::cases()),
            'name' => fake()->name(),
            'sort_order' => 0,
        ];
    }
}
