<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\PipelineStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\RetryProcessingRequest;
use App\Models\Record;
use App\Services\Records\ProcessingRetry;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RetryProcessingController extends Controller
{
    /**
     * Run the whole pipeline again (after a failure or a cancel), or one
     * failed or skipped stage and the stages that read its results. Progress
     * arrives over Reverb as it does after an upload.
     */
    public function __invoke(RetryProcessingRequest $request, Record $record, ProcessingRetry $retry): JsonResponse
    {
        try {
            $plan = $retry->retry($record, $request->pipelineOptions(), $request->stage());
        } catch (ConflictHttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'queued' => true,
            'stages' => array_map(fn (PipelineStage $stage): string => $stage->value, $plan),
        ], 202);
    }
}
