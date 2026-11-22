<?php

namespace App\Jobs;

use App\Enums\ProcessingStatus;
use App\Models\Record;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\PipelineRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Asks the AI service to download a URL import (POST /import-url). The file
 * lands in the record's master folder under a new file id; the AI service
 * reports it on /internal/imports/{record}/done, which starts processing.
 */
class StartImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Record $record) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(AiServiceClient $client): void
    {
        if ($this->isCancelled() || $this->record->source_url === null) {
            return;
        }

        try {
            $jobId = $client->importUrl([
                'record_id' => $this->record->id,
                'record_file_id' => (string) Str::ulid(),
                'url' => $this->record->source_url,
                'output_dir' => "records/{$this->record->id}/master",
                'max_bytes' => (int) config('archive.max_upload_mb') * 1024 * 1024,
                'max_duration_seconds' => (int) config('archive.max_video_minutes') * 60,
            ]);
        } catch (RequestException $exception) {
            if ($exception->response->clientError() && $exception->response->status() !== 429) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        $stored = Record::query()
            ->whereKey($this->record->id)
            ->where('processing_status', '!=', ProcessingStatus::Cancelled)
            ->update(['processing_job_id' => $jobId]);

        if ($stored === 0) {
            CancelProcessingJob::dispatch($this->record->id, $jobId);

            return;
        }

        Log::info('URL import started', ['record_id' => $this->record->id, 'job_id' => $jobId]);
    }

    /**
     * The download never started: fail it, and skip the stages that needed the file.
     */
    public function failed(?Throwable $exception): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $message = $exception instanceof RequestException && $exception->response->clientError()
            ? 'The AI service rejected the import request.'
            : 'The AI service could not be reached to start the download.';

        app(PipelineRecorder::class)->failImport($this->record, $message);

        Log::warning('URL import could not start', ['record_id' => $this->record->id, 'error' => $exception?->getMessage()]);
    }

    private function isCancelled(): bool
    {
        return Record::withTrashed()->whereKey($this->record->id)->where('processing_status', ProcessingStatus::Cancelled)->exists();
    }
}
