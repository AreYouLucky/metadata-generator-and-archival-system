<?php

namespace App\Jobs;

use App\Enums\BatchZipStatus;
use App\Events\BatchProgressUpdated;
use App\Models\Batch;
use App\Services\Batches\BatchArchive;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds a batch's ZIP of exports in the background (PROJECT_SPEC §3), one
 * build per batch at a time; the batch page hears when it is ready.
 */
class BuildBatchZipJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Batch $batch,
        public bool $thumbnails = false,
        public ?int $userId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->batch->id;
    }

    public function handle(BatchArchive $archive): void
    {
        $archive->build($this->batch, $this->thumbnails, $this->userId);
    }

    public function failed(?Throwable $exception): void
    {
        $this->batch->zip_status = BatchZipStatus::Failed;
        $this->batch->save();

        rescue(fn () => BatchProgressUpdated::dispatch($this->batch), report: false);

        Log::warning('Batch ZIP failed', ['batch_id' => $this->batch->id, 'error' => $exception?->getMessage()]);
    }
}
