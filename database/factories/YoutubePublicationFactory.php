<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Enums\YouTubePrivacy;
use App\Models\Record;
use App\Models\YoutubeAccount;
use App\Models\YoutubePublication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YoutubePublication>
 */
class YoutubePublicationFactory extends Factory
{
    /**
     * A publication waiting to upload.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'record_id' => Record::factory(),
            'youtube_account_id' => YoutubeAccount::factory(),
            'user_id' => fn (array $attributes) => YoutubeAccount::query()->whereKey($attributes['youtube_account_id'])->value('user_id'),
            'channel_id' => fn (array $attributes) => YoutubeAccount::query()->whereKey($attributes['youtube_account_id'])->value('channel_id'),
            'channel_title' => fn (array $attributes) => YoutubeAccount::query()->whereKey($attributes['youtube_account_id'])->value('channel_title'),
            'status' => PublicationStatus::Queued,
            'privacy_status' => YouTubePrivacy::Private,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'tags' => ['archive', 'video'],
            'category_id' => '22',
            'made_for_kids' => false,
            'with_thumbnail' => false,
        ];
    }

    public function uploading(int $fileSize = 1000, int $uploaded = 0): static
    {
        return $this->state([
            'status' => PublicationStatus::Uploading,
            'upload_session_url' => 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&upload_id=test-session',
            'file_size' => $fileSize,
            'bytes_uploaded' => $uploaded,
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => PublicationStatus::Processing,
            'video_id' => fake()->regexify('[A-Za-z0-9_-]{11}'),
            'youtube_upload_status' => 'uploaded',
        ]);
    }

    public function published(): static
    {
        return $this->state([
            'status' => PublicationStatus::Published,
            'video_id' => fake()->regexify('[A-Za-z0-9_-]{11}'),
            'youtube_upload_status' => 'processed',
            'youtube_processing_status' => 'succeeded',
            'published_at' => now(),
        ]);
    }
}
