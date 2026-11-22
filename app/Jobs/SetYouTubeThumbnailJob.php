<?php

namespace App\Jobs;

use App\Models\YoutubePublication;
use App\Services\YouTube\YouTubePublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends the record's thumbnail to a video that has just been uploaded. A
 * refusal is noted on the publication; only outages are retried.
 */
class SetYouTubeThumbnailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public YoutubePublication $publication) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(YouTubePublisher $publisher): void
    {
        $publisher->setThumbnail($this->publication);
    }

    public function failed(?Throwable $exception): void
    {
        $this->publication->forceFill(['thumbnail_error' => 'YouTube could not be reached to set the thumbnail.'])->save();
    }
}
