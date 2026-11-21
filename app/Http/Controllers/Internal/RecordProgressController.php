<?php

namespace App\Http\Controllers\Internal;

use App\Enums\PipelineStage;
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
     */
    public function __invoke(ReceiveRecordProgressRequest $request, Record $record, PipelineRecorder $recorder): JsonResponse
    {
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
