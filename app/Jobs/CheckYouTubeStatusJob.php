<?php

namespace App\Jobs;

use App\Models\YoutubePublication;
use App\Services\YouTube\YouTubeException;
use App\Services\YouTube\YouTubePublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Follows YouTube's processing of an uploaded video: checks again every
 * `archive.youtube.status_check_seconds`, at most `status_checks` times,
 * until it is processed, failed or rejected. After that the Publish panel's
 * "Check status" asks once more.
 */
class CheckYouTubeStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public YoutubePublication $publication, public int $check = 1) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(YouTubePublisher $publisher): void
    {
        try {
            $final = $publisher->checkStatus($this->publication);
        } catch (YouTubeException $exception) {
            if ($exception->transient) {
                throw $exception;
            }

            // E.g. access revoked: the video itself is fine, so the publication is left as it is.
            return;
        }

        if (! $final && $this->check < (int) config('archive.youtube.status_checks')) {
            self::dispatch($this->publication, $this->check + 1)->delay(now()->addSeconds((int) config('archive.youtube.status_check_seconds')));
        }
    }
}
