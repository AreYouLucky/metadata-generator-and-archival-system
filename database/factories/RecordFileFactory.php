<?php

namespace Database\Factories;

use App\Enums\ChecksumStatus;
use App\Enums\FileRole;
use App\Models\Record;
use App\Models\RecordFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RecordFile>
 */
class RecordFileFactory extends Factory
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
            'role' => FileRole::Master,
            'storage_location' => 'records/'.Str::ulid().'.mp4',
            'original_filename' => fake()->slug(2).'.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => fake()->numberBetween(1_000_000, 500_000_000),
            'md5_checksum' => fake()->md5(),
            'sha256_checksum' => fake()->sha256(),
            'checksum_status' => ChecksumStatus::Ok,
        ];
    }
}
