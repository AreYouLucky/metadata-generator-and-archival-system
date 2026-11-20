<?php

namespace Database\Factories;

use App\Enums\ProcessingStatus;
use App\Enums\ReviewStatus;
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
            'review_status' => ReviewStatus::Draft,
        ];
    }

    /**
     * A processed record submitted for approval.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => ProcessingStatus::Ready,
            'review_status' => ReviewStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    /**
     * A processed record that an admin has approved and published.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => ProcessingStatus::Ready,
            'review_status' => ReviewStatus::Approved,
            'submitted_at' => now()->subDays(2),
            'reviewed_at' => now()->subDay(),
            'published_at' => now()->subDay(),
        ]);
    }
}
