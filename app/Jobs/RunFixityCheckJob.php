<?php

namespace App\Jobs;

use App\Enums\FixityTrigger;
use App\Models\RecordFile;
use App\Services\AiService\AiServiceClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks the AI service to re-hash a stored file (POST /jobs/fixity). One check
 * per file at a time; the result arrives on /internal/record-files/{file}/fixity.
 */
class RunFixityCheckJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    public function __construct(
        public RecordFile $file,
        public FixityTrigger $trigger,
    ) {}

    public function uniqueId(): string
    {
        return $this->file->id;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 300, 900];
    }

    public function handle(AiServiceClient $client): void
    {
        try {
            $client->fixity([
                'record_file_id' => $this->file->id,
                'path' => $this->file->storage_location,
                'triggered_by' => $this->trigger->value,
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
        Log::warning('Fixity check could not start', ['record_id' => $this->file->record_id, 'record_file_id' => $this->file->id, 'error' => $exception?->getMessage()]);
    }
}
