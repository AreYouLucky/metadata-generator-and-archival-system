<?php

namespace App\Jobs;

use App\Enums\FileRole;
use App\Enums\PipelineStage;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Models\Record;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\PipelineOptions;
use App\Services\Records\PipelineRecorder;
use App\Services\Records\PipelineSeed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hands a record's master file to the AI service (POST /jobs/process). The
 * service then reports each stage back on the signed /internal callbacks.
 * With `$stages`, only those run again (a retry of one stage and the stages
 * that read its results); the stored results the others produced go along
 * as the seed.
 */
class StartProcessingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * A record deleted while the job waited needs no processing.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  list<PipelineStage>|null  $stages  null runs every stage
     */
    public function __construct(
        public Record $record,
        public PipelineOptions $options,
        public ?array $stages = null,
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

    public function handle(AiServiceClient $client, PipelineSeed $seed): void
    {
        // Cancelled while this job waited in the queue.
        if ($this->isCancelled()) {
            return;
        }

        $file = $this->record->files()->where('role', FileRole::Master)->firstOrFail();

        try {
            $jobId = $client->process([
                'record_id' => $this->record->id,
                'record_file_id' => $file->id,
                'video_path' => $file->storage_location,
                'options' => [
                    'preset' => $this->options->presetRules(),
                    'custom_prompt' => $this->options->customPrompt,
                    'title_count' => PipelineOptions::TITLE_COUNT,
                    'max_duration_seconds' => config('archive.max_video_minutes') * 60,
                    ...$this->options->jobFlags(),
                    'stages' => $this->stages === null ? null : array_map(fn (PipelineStage $stage): string => $stage->value, $this->stages),
                ],
                ...($this->stages === null ? [] : ['seed' => $seed->for($this->record, $this->stages)]),
            ]);
        } catch (RequestException $exception) {
            // A rejected request won't succeed on retry; only outages and throttling are worth waiting out.
            if (self::wasRejected($exception)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        // Kept so "Cancel" can stop the run. A cancel that landed while the
        // request was in flight found no job id, so it is passed on here.
        $stored = Record::query()
            ->whereKey($this->record->id)
            ->where('processing_status', '!=', ProcessingStatus::Cancelled)
            ->update(['processing_job_id' => $jobId]);

        if ($stored === 0) {
            CancelProcessingJob::dispatch($this->record->id, $jobId);

            return;
        }

        Log::info('Processing started', ['record_id' => $this->record->id, 'job_id' => $jobId]);
    }

    /**
     * The AI service never accepted the job: fail every stage so the record
     * doesn't sit in "queued" forever.
     */
    public function failed(?Throwable $exception): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $recorder = app(PipelineRecorder::class);

        $message = $exception instanceof RequestException && self::wasRejected($exception)
            ? 'The AI service rejected the processing request.'
            : 'The AI service could not be reached to start processing.';

        foreach ($this->stages ?? PipelineStage::active() as $stage) {
            $recorder->progress($this->record, $stage, StageStatus::Failed, message: $message);
        }

        Log::warning('Processing could not start', ['record_id' => $this->record->id, 'error' => $exception?->getMessage()]);
    }

    private function isCancelled(): bool
    {
        return Record::withTrashed()->whereKey($this->record->id)->where('processing_status', ProcessingStatus::Cancelled)->exists();
    }

    private static function wasRejected(RequestException $exception): bool
    {
        return $exception->response->clientError() && $exception->response->status() !== 429;
    }
}
