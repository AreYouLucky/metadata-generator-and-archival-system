<?php

namespace App\Http\Controllers\Internal;

use App\Enums\PipelineStage;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ReceiveRecordProgressRequest;
use App\Models\Record;
use App\Services\Records\PipelineRecorder;
use Illuminate\Http\JsonResponse;

class RecordProgressController extends Controller
{
    /**
     * A stage started, moved forward, failed or was skipped (signed by the AI service).
     * Ignored once the encoder cancelled processing; answered with 200 so the
     * service doesn't retry.
     */
    public function __invoke(ReceiveRecordProgressRequest $request, Record $record, PipelineRecorder $recorder): JsonResponse
    {
        if ($record->processing_status === ProcessingStatus::Cancelled) {
            return response()->json(['ok' => true, 'ignored' => 'cancelled']);
        }

        $recorder->progress(
            $record,
            PipelineStage::from($request->validated('stage')),
            StageStatus::from($request->validated('status')),
            (int) $request->validated('progress'),
            $request->validated('message'),
        );

        return response()->json(['ok' => true]);
    }
}
