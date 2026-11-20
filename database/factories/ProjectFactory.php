<?php

namespace Database\Factories;

use App\Enums\ChecksumStatus;
use App\Enums\ProjectStatus;
use App\Enums\ReviewStatus;
use App\Enums\SourceType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->encoder(),
            'title' => fake()->sentence(4),
            'status' => ProjectStatus::Queued,
            'review_status' => ReviewStatus::Draft,
            'source_type' => SourceType::Upload,
            'original_filename' => fake()->slug(2).'.mp4',
            'storage_path' => 'uploads/'.Str::ulid().'.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => fake()->numberBetween(1_000_000, 500_000_000),
            'options' => [],
            'checksum_status' => ChecksumStatus::Pending,
        ];
    }

    /**
     * A processed project whose record has been approved and published.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Ready,
            'review_status' => ReviewStatus::Approved,
            'submitted_at' => now()->subDays(2),
            'reviewed_at' => now()->subDay(),
            'published_at' => now()->subDay(),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Ready,
            'review_status' => ReviewStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
