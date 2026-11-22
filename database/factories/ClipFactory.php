<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Clip;
use App\Models\Record;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clip>
 */
class ClipFactory extends Factory
{
    /**
     * A clip still waiting to be cut.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->randomFloat(1, 0, 300);

        return [
            'record_id' => Record::factory(),
            'created_by' => fn (array $attributes) => Record::query()->whereKey($attributes['record_id'])->value('created_by'),
            'title' => fake()->sentence(3),
            'start' => $start,
            'end' => $start + 20,
            'status' => TaskStatus::Queued,
        ];
    }

    public function processing(): static
    {
        return $this->state(['status' => TaskStatus::Processing]);
    }

    /**
     * A clip that was cut, with its checksums (the file itself is not created).
     */
    public function done(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Done,
            'path' => null,
            'file_size' => 1_048_576,
            'md5_checksum' => str_repeat('a', 32),
            'sha256_checksum' => str_repeat('b', 64),
            'technical' => ['file_format' => 'mp4', 'duration' => 20.0, 'video_codec' => 'h264'],
            'finished_at' => now(),
        ])->afterCreating(function (Clip $clip): void {
            $clip->forceFill(['path' => $clip->storagePath()])->save();
        });
    }
}
