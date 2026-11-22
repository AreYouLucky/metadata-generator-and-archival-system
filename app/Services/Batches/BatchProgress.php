<?php

namespace App\Services\Batches;

use App\Enums\BatchStatus;
use App\Enums\ProcessingStatus;
use App\Events\BatchProgressUpdated;
use App\Models\Batch;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a batch's counts in step with its records: completed (ready), failed
 * (failed or cancelled) and total (records not deleted). The batch is done
 * once every record has finished one way or the other. Counted in one
 * grouped query, so it stays cheap however often a record changes status.
 */
class BatchProgress
{
    public function refresh(string $batchId): void
    {
        $counts = DB::table('records')
            ->where('batch_id', $batchId)
            ->whereNull('deleted_at')
            ->selectRaw('processing_status, count(*) as aggregate')
            ->groupBy('processing_status')
            ->pluck('aggregate', 'processing_status');

        $batch = Batch::query()->find($batchId);

        if ($batch === null) {
            return;
        }

        $count = fn (ProcessingStatus ...$statuses): int => (int) array_sum(array_map(
            fn (ProcessingStatus $status): int => (int) ($counts[$status->value] ?? 0),
            $statuses,
        ));

        $batch->total = (int) $counts->sum();
        $batch->completed = $count(ProcessingStatus::Ready);
        $batch->failed = $count(ProcessingStatus::Failed, ProcessingStatus::Cancelled);
        $batch->status = $batch->completed + $batch->failed >= $batch->total ? BatchStatus::Done : BatchStatus::Processing;

        if ($batch->isDirty()) {
            $batch->save();
            rescue(fn () => BatchProgressUpdated::dispatch($batch), report: false);
        }
    }
}
