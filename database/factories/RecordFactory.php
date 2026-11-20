<?php

namespace Database\Factories;

use App\Enums\ProcessingStatus;
use App\Enums\SecurityClassification;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Record>
 */
class RecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->encoder(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'language' => 'en',
            'date_recorded' => fake()->dateTimeBetween('-5 years', 'now'),
            'security_classification' => SecurityClassification::Unclassified,
            'processing_status' => ProcessingStatus::Queued,
            'is_approved' => false,
        ];
    }

    /**
     * A processed record that an admin has approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => ProcessingStatus::Ready,
            'is_approved' => true,
        ]);
    }
}
