<?php

namespace App\Http\Controllers\Internal;

use App\Enums\FileRole;
use App\Enums\PipelineStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ReceiveStageResultRequest;
use App\Models\Record;
use App\Models\RecordFile;
use App\Services\Records\PipelineRecorder;
use Illuminate\Http\JsonResponse;

class RecordStageResultController extends Controller
{
    /**
     * A stage finished; store its result (signed by the AI service).
     */
    public function __invoke(ReceiveStageResultRequest $request, Record $record, PipelineStage $stage, PipelineRecorder $recorder): JsonResponse
    {
        match ($stage) {
            PipelineStage::Checksum => $recorder->storeChecksum($record, $this->master($record), $request->checksumResult()),
            PipelineStage::Probe => $recorder->storeProbe($record, $this->master($record), $request->validated()),
            PipelineStage::Transcribe => $recorder->storeTranscript($record, $request->transcriptResult()),
            PipelineStage::GenerateMetadata => $recorder->storeMetadata($record, $request->metadataResult()),
            default => $recorder->complete($record, $stage),
        };

        return response()->json(['ok' => true]);
    }

    private function master(Record $record): RecordFile
    {
        return $record->files()->where('role', FileRole::Master)->firstOrFail();
    }
}
