<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Record;
use App\Models\RecordTranscript;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordTranscript>
 */
class RecordTranscriptFactory extends Factory
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
            'language' => 'en',
            'is_original' => true,
            'status' => TaskStatus::Done,
        ];
    }

    /**
     * A finished translation into `$language`.
     */
    public function translation(string $language = 'fil'): static
    {
        return $this->state(['language' => $language, 'is_original' => false]);
    }
}
