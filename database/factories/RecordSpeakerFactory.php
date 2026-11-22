<?php

namespace Database\Factories;

use App\Models\Record;
use App\Models\RecordSpeaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordSpeaker>
 */
class RecordSpeakerFactory extends Factory
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
            'label' => 'S'.fake()->unique()->numberBetween(1, 999),
            'display_name' => null,
        ];
    }
}
