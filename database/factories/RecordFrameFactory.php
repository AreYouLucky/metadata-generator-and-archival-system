<?php

namespace Database\Factories;

use App\Models\Record;
use App\Models\RecordFrame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordFrame>
 */
class RecordFrameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $time = fake()->randomFloat(3, 0, 600);

        return [
            'record_id' => Record::factory(),
            'time_sec' => $time,
            'path' => fn (array $attributes): string => "records/{$attributes['record_id']}/frames/000_".((int) round($time * 1000)).'.jpg',
            'is_scene_start' => false,
            'description' => null,
            'ocr_text' => null,
            'objects' => null,
            'face_count' => null,
        ];
    }

    /**
     * A frame the visual stages have analysed.
     */
    public function analysed(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => 'A speaker at a podium in front of a banner.',
            'ocr_text' => 'Regional S&T Fair 2026',
            'objects' => [['label' => 'person', 'confidence' => 0.91]],
            'face_count' => 1,
        ]);
    }
}
