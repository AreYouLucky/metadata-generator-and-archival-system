<?php

namespace App\Services\Records;

use App\Enums\FileRole;
use App\Enums\PipelineStage;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Jobs\StartImportJob;
use App\Jobs\StartProcessingJob;
use App\Models\Record;
use App\Models\RecordStage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Runs processing again: the whole pipeline after a failure or a cancel, or
 * one stage and those that read its results (PROJECT_SPEC §13, Phase 6). A
 * URL import whose download never finished downloads again first. The
 * options chosen for the retry are kept on the record.
 */
class ProcessingRetry
{
    public function __construct(private PipelineRecorder $recorder) {}

    /**
     * @return list<PipelineStage> the stages queued again
     *
     * @throws ConflictHttpException when the record or stage can't be retried now
     */
    public function retry(Record $record, PipelineOptions $options, ?PipelineStage $stage = null): array
    {
        [$plan, $import] = DB::transaction(function () use ($record, $options, $stage): array {
            // Locked so a double click can't queue the pipeline twice.
            $locked = Record::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail(['id', 'processing_status', 'source_url']);
            $hasMaster = $record->files()->where('role', FileRole::Master)->exists();
            $needsDownload = ! $hasMaster && $locked->source_url !== null;

            if ($stage === null || $stage === PipelineStage::Import) {
                if ($stage === PipelineStage::Import && ! $needsDownload) {
                    throw new ConflictHttpException('The video has already been downloaded.');
                }

                if (! in_array($locked->processing_status, [ProcessingStatus::Failed, ProcessingStatus::Cancelled], true)) {
                    throw new ConflictHttpException('Only a record whose processing failed or was cancelled can be retried.');
                }

                $plan = PipelineStage::active();
                $this->recorder->resetStages($record, $needsDownload ? PipelineStage::tracked() : $plan);
            } else {
                $plan = $this->planStage($record, $locked, $stage, $hasMaster);
                $this->recorder->resetStages($record, $plan);
            }

            $record->pipeline_options = $options->toArray();
            $record->saveQuietly();

            return [$plan, $needsDownload];
        });

        if ($import) {
            StartImportJob::dispatch($record);
        } else {
            StartProcessingJob::dispatch($record, $options, $stage === null ? null : $plan);
        }

        return $plan;
    }

    /**
     * The stages a retry of `$stage` runs, after checking it may run.
     *
     * @return list<PipelineStage>
     */
    private function planStage(Record $record, Record $locked, PipelineStage $stage, bool $hasMaster): array
    {
        $rows = $record->stages()->get(['stage', 'status'])->keyBy(fn (RecordStage $row): string => $row->stage->value);
        $refusal = self::refusal($locked->processing_status, $rows->map(fn (RecordStage $row): StageStatus => $row->status)->all(), $stage, $hasMaster);

        if ($refusal !== null) {
            throw new ConflictHttpException($refusal);
        }

        return $stage->withDependents();
    }

    /**
     * Why one step can't be retried on its own now; null when it can: the
     * record has finished processing and has its video, the step failed or
     * was skipped (or never ran, on records from before its phase), and
     * what it reads is done. The workspace asks this for every step.
     *
     * @param  array<string, StageStatus>  $statuses  by stage value
     */
    public static function refusal(ProcessingStatus $processing, array $statuses, PipelineStage $stage, bool $hasMaster): ?string
    {
        if ($stage === PipelineStage::Import) {
            return 'Retry the whole import instead.';
        }

        if (! in_array($processing, [ProcessingStatus::Failed, ProcessingStatus::Ready], true)) {
            return 'A single step can be retried only once processing has finished.';
        }

        if (! $hasMaster) {
            return 'The record has no video file yet.';
        }

        $status = $statuses[$stage->value] ?? null;

        if ($status !== null && ! in_array($status, [StageStatus::Failed, StageStatus::Skipped], true)) {
            return "The {$stage->label()} step can be retried only after it failed or was skipped.";
        }

        foreach ($stage->dependsOn() as $dependency) {
            if (($statuses[$dependency->value] ?? null) !== StageStatus::Done) {
                return "Retry the {$dependency->label()} step first: {$stage->label()} needs its result.";
            }
        }

        return null;
    }
}
