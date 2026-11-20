<?php

namespace Database\Factories;

use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordSegment>
 */
class RecordSegmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'record_transcript_id' => RecordTranscript::factory(),
            'position' => $position = fake()->numberBetween(0, 500),
            'start' => $start = $position * 4.0,
            'end' => $start + fake()->randomFloat(3, 1, 4),
            'text' => $text = fake()->sentence(),
            'original_text' => $text,
            'is_edited' => false,
        ];
    }
}
