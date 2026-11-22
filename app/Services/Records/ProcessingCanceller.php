<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Enums\PipelineStage;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Jobs\CancelProcessingJob;
use App\Models\Record;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stops a record's pipeline: stages that haven't finished become `cancelled`
 * (results already stored stay), the record becomes `cancelled`, and the AI
 * service is told to stop the run. Callbacks that arrive afterwards are
 * ignored (RecordProgressController, RecordStageResultController). A retry
 * runs every stage again.
 */
class ProcessingCanceller
{
    public function __construct(
        private PipelineRecorder $recorder,
        private AuditTrail $audit,
    ) {}

    public static function isCancellable(Record $record): bool
    {
        return in_array($record->processing_status, [ProcessingStatus::Queued, ProcessingStatus::Processing], true);
    }

    /**
     * False when there was nothing to cancel (already finished, failed or cancelled).
     */
    public function cancel(Record $record, ?User $user): bool
    {
        $jobId = DB::transaction(function () use ($record, $user): string|false|null {
            // Locked so a cancel can't cross a retry or a second click.
            $locked = Record::query()->whereKey($record->getKey())->lockForUpdate()->first(['id', 'processing_status', 'processing_job_id']);

            if ($locked === null || ! self::isCancellable($locked)) {
                return false;
            }

            $stopped = $record->stages()
                ->whereIn('stage', PipelineStage::tracked())
                ->whereIn('status', [StageStatus::Pending, StageStatus::Running])
                ->update([
                    'status' => StageStatus::Cancelled,
                    'message' => $user === null ? 'Cancelled.' : "Cancelled by {$user->name}.",
                    'finished_at' => now(),
                ]);

            $this->recorder->refreshStatus($record);

            // Every stage had already finished; the status was just out of date.
            if ($stopped === 0) {
                return false;
            }

            $this->audit->log($record, AuditAction::Updated, $user, 'Processing cancelled.');

            return $locked->processing_job_id;
        });

        if ($jobId === false) {
            return false;
        }

        // Without a job id the pipeline hasn't started; StartProcessingJob checks the status first.
        if ($jobId !== null) {
            CancelProcessingJob::dispatch($record->id, $jobId)->afterCommit();
        }

        return true;
    }
}
