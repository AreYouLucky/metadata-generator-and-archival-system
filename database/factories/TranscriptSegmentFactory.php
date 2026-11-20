<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TranscriptSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranscriptSegment>
 */
class TranscriptSegmentFactory extends Factory
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
            'position' => $position = fake()->numberBetween(0, 500),
            'start' => $start = $position * 4.0,
            'end' => $start + fake()->randomFloat(3, 1, 4),
            'text' => $text = fake()->sentence(),
            'original_text' => $text,
            'is_edited' => false,
        ];
    }
}
