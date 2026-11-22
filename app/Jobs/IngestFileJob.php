<?php

namespace App\Jobs;

use App\Models\RecordFile;
use App\Services\AiService\AiServiceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hands an added file to the AI service to be checksummed and probed
 * (POST /jobs/ingest-file); the results arrive on the signed callbacks.
 */
class IngestFileJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public RecordFile $file) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(AiServiceClient $client): void
    {
        try {
            $client->ingestFile([
                'record_id' => $this->file->record_id,
                'record_file_id' => $this->file->id,
                'path' => $this->file->storage_location,
                'role' => $this->file->role->value,
            ]);
        } catch (RequestException $exception) {
            if ($exception->response->clientError() && $exception->response->status() !== 429) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('File ingest could not start', ['record_id' => $this->file->record_id, 'record_file_id' => $this->file->id, 'error' => $exception?->getMessage()]);
    }
}
