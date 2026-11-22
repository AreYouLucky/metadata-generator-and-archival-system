<?php

namespace App\Http\Controllers\Internal;

use App\Enums\ProcessingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ReceiveImportResultRequest;
use App\Models\Record;
use App\Services\Records\ImportRecorder;
use App\Services\Records\PipelineRecorder;
use Illuminate\Http\JsonResponse;

class ImportResultController extends Controller
{
    /**
     * A URL import finished downloading or failed (signed by the AI service).
     * Ignored once the encoder cancelled; answered with 200 so the service
     * doesn't retry.
     */
    public function __invoke(ReceiveImportResultRequest $request, Record $record, ImportRecorder $imports, PipelineRecorder $recorder): JsonResponse
    {
        if ($record->processing_status === ProcessingStatus::Cancelled || $record->source_url === null) {
            return response()->json(['ok' => true, 'ignored' => $record->source_url === null ? 'not an import' : 'cancelled']);
        }

        if ($request->validated('status') === 'failed') {
            $recorder->failImport($record, (string) $request->validated('error'));

            return response()->json(['ok' => true]);
        }

        $imports->store($record, (string) $request->validated('path'), (int) $request->validated('file_size'), $request->source());

        return response()->json(['ok' => true]);
    }
}
