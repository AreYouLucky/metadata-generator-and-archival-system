<?php

namespace App\Jobs;

use App\Services\AiService\AiServiceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the AI service to stop a pipeline run (POST /jobs/{job_id}/cancel).
 * The record is already marked cancelled and Laravel ignores the run's late
 * callbacks, so this only saves the work (and AI cost) still to come.
 */
class CancelProcessingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public string $recordId,
        public string $jobId,
    ) {}

    /**
     * Back off while the AI service restarts.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(AiServiceClient $client): void
    {
        try {
            $client->cancel($this->jobId);
        } catch (RequestException $exception) {
            // A rejected request won't succeed on retry; only outages and throttling are worth waiting out.
            if ($exception->response->clientError() && $exception->response->status() !== 429) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        Log::info('Processing cancelled', ['record_id' => $this->recordId, 'job_id' => $this->jobId]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('The AI service could not be told to cancel processing', [
            'record_id' => $this->recordId,
            'job_id' => $this->jobId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
