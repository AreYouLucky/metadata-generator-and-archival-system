<?php

namespace App\Jobs;

use App\Models\YoutubePublication;
use App\Services\YouTube\YouTubeException;
use App\Services\YouTube\YouTubePublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One step of a YouTube upload (YouTubePublisher::step): sends chunks for
 * `archive.youtube.step_seconds`, then queues the next step, so no job runs
 * past the queue timeout. Outages and rate limits are retried with backoff
 * (the next try asks YouTube where the upload got to); other refusals fail
 * the publication with YouTube's reason. Once uploaded, the thumbnail is set
 * and YouTube's processing is followed.
 */
class PublishToYouTubeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** Below Horizon's 60 s: a step plus one slow chunk. */
    public int $timeout = 58;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public YoutubePublication $publication) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60, 180, 600, 1800];
    }

    /**
     * One step per publication at a time.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("youtube-publication:{$this->publication->id}"))->releaseAfter(30)->expireAfter(120)];
    }

    public function handle(YouTubePublisher $publisher): void
    {
        try {
            $result = $publisher->step($this->publication);
        } catch (YouTubeException $exception) {
            if ($exception->transient) {
                throw $exception;
            }

            $publisher->fail($this->publication, $exception->getMessage());

            return;
        }

        if ($result === YouTubePublisher::MORE) {
            // After this step's overlap lock is released, so the next step isn't bounced.
            self::dispatch($this->publication)->delay(now()->addSeconds(2));
        } elseif ($result === YouTubePublisher::UPLOADED) {
            SetYouTubeThumbnailJob::dispatch($this->publication);
            CheckYouTubeStatusJob::dispatch($this->publication)->delay(now()->addSeconds((int) config('archive.youtube.status_check_seconds')));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('YouTube upload failed', ['record_id' => $this->publication->record_id, 'publication_id' => $this->publication->id, 'error' => $exception?->getMessage()]);

        app(YouTubePublisher::class)->fail(
            $this->publication,
            $exception instanceof YouTubeException ? $exception->getMessage() : 'The upload to YouTube failed. Try publishing again.',
        );
    }
}
