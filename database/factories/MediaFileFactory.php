<?php

namespace Database\Factories;

use App\Enums\ChecksumStatus;
use App\Enums\MediaFileRole;
use App\Models\MediaFile;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaFile>
 */
class MediaFileFactory extends Factory
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
            'role' => MediaFileRole::Master,
            'path' => 'media/'.Str::ulid().'.mp4',
            'original_filename' => fake()->slug(2).'.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => fake()->numberBetween(1_000_000, 500_000_000),
            'container' => 'mov,mp4,m4a,3gp,3g2,mj2',
            'duration_sec' => fake()->randomFloat(3, 30, 3600),
            'md5' => fake()->md5(),
            'sha256' => fake()->sha256(),
            'checksum_status' => ChecksumStatus::Ok,
        ];
    }
}
