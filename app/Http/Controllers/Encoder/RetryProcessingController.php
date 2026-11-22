<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\ProcessingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\RetryProcessingRequest;
use App\Jobs\StartProcessingJob;
use App\Models\Record;
use App\Services\Records\PipelineRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RetryProcessingController extends Controller
{
    /**
     * Run the whole pipeline again for a record whose processing failed or was cancelled.
     * Progress arrives over Reverb as it does after an upload.
     */
    public function __invoke(RetryProcessingRequest $request, Record $record, PipelineRecorder $recorder): JsonResponse
    {
        $restarted = DB::transaction(function () use ($record, $recorder): bool {
            // Locked so a double click can't queue the pipeline twice.
            $locked = Record::query()->whereKey($record->getKey())->lockForUpdate()->first(['id', 'processing_status']);

            if (! in_array($locked?->processing_status, [ProcessingStatus::Failed, ProcessingStatus::Cancelled], true)) {
                return false;
            }

            $recorder->resetStages($record);

            return true;
        });

        abort_unless($restarted, 409, 'Only a record whose processing failed or was cancelled can be retried.');

        StartProcessingJob::dispatch($record, $request->pipelineOptions());

        return response()->json(['queued' => true], 202);
    }
}
