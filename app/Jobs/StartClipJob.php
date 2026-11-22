<?php

namespace App\Jobs;

use App\Enums\FileRole;
use App\Enums\TaskStatus;
use App\Models\Clip;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\ClipCutter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks the AI service to cut a clip from the master (POST /clips); the file,
 * its checksums and probe arrive on the signed
 * /internal/records/{record}/clips/{clip} callback.
 */
class StartClipJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** A clip deleted while this waited needs no cut. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Clip $clip) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(AiServiceClient $client, ClipCutter $cutter): void
    {
        if ($this->clip->status !== TaskStatus::Queued) {
            return;
        }

        $master = $this->clip->record->files()->where('role', FileRole::Master)->first(['id', 'record_id', 'storage_location']);

        if ($master === null) {
            $cutter->failed($this->clip, 'The record has no master file to cut from.');

            return;
        }

        try {
            $client->cutClip([
                'record_id' => $this->clip->record_id,
                'clip_id' => $this->clip->id,
                'video_path' => $master->storage_location,
                'start' => $this->clip->start,
                'end' => $this->clip->end,
                'output_path' => $this->clip->storagePath(),
                'max_height' => (int) config('archive.clips.max_height'),
            ]);
        } catch (RequestException $exception) {
            // A refused request fails again on retry; outages and throttling are waited out.
            if ($exception->response->clientError() && $exception->response->status() !== 429) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        $cutter->started($this->clip);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Clip could not start', ['record_id' => $this->clip->record_id, 'clip_id' => $this->clip->id, 'error' => $exception?->getMessage()]);
        $clip = $this->clip->fresh();

        if ($clip?->isBusy() === true) {
            app(ClipCutter::class)->failed($clip, 'The AI service could not start the cut. Try again later.');
        }
    }
}
