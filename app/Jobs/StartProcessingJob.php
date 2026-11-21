<?php

namespace App\Jobs;

use App\Enums\FileRole;
use App\Enums\PipelineStage;
use App\Enums\StageStatus;
use App\Models\Record;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\PipelineOptions;
use App\Services\Records\PipelineRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hands a record's master file to the AI service (POST /jobs/process). The
 * service then reports each stage back on the signed /internal callbacks.
 */
class StartProcessingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public Record $record,
        public PipelineOptions $options,
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
        $file = $this->record->files()->where('role', FileRole::Master)->firstOrFail();

        try {
            $client->process([
                'record_id' => $this->record->id,
                'record_file_id' => $file->id,
                'video_path' => $file->storage_location,
                'options' => [
                    'preset' => $this->options->presetRules(),
                    'custom_prompt' => $this->options->customPrompt,
                    'title_count' => PipelineOptions::TITLE_COUNT,
                    'max_duration_seconds' => config('archive.max_video_minutes') * 60,
                ],
            ]);
        } catch (RequestException $exception) {
            // A rejected request won't succeed on retry; only outages and throttling are worth waiting out.
            if (self::wasRejected($exception)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        Log::info('Processing started', ['record_id' => $this->record->id]);
    }

    /**
     * The AI service never accepted the job: fail every stage so the record
     * doesn't sit in "queued" forever.
     */
    public function failed(?Throwable $exception): void
    {
        $recorder = app(PipelineRecorder::class);

        $message = $exception instanceof RequestException && self::wasRejected($exception)
            ? 'The AI service rejected the processing request.'
            : 'The AI service could not be reached to start processing.';

        foreach (PipelineStage::active() as $stage) {
            $recorder->progress($this->record, $stage, StageStatus::Failed, message: $message);
        }

        Log::warning('Processing could not start', ['record_id' => $this->record->id, 'error' => $exception?->getMessage()]);
    }

    private static function wasRejected(RequestException $exception): bool
    {
        return $exception->response->clientError() && $exception->response->status() !== 429;
    }
}
