<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\PipelineStage;
use App\Enums\StageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\GenerateMetadataRequest;
use App\Http\Requests\Encoder\UpdateMetadataRequest;
use App\Jobs\GenerateMetadataJob;
use App\Models\Record;
use App\Models\RecordMetadata;
use App\Services\Records\PipelineRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MetadataController extends Controller
{
    /**
     * Regenerate (preset, custom prompt). Queued; the result arrives over Reverb
     * as a completed `generate_metadata` stage.
     */
    public function store(GenerateMetadataRequest $request, Record $record, PipelineRecorder $recorder): JsonResponse
    {
        // From the transcript, the frames' on-screen content (Phase 5), or both.
        abort_if(
            $record->originalTranscript()->doesntExist() && GenerateMetadataJob::visualContext($record) === [],
            422,
            'The record has no transcript or analysed frames yet.',
        );

        $running = $record->stages()
            ->where('stage', PipelineStage::GenerateMetadata)
            ->where('status', StageStatus::Running)
            ->exists();
        abort_if($running, 409, 'Metadata is already being generated.');

        $recorder->progress($record, PipelineStage::GenerateMetadata, StageStatus::Running, 0, 'Queued');
        GenerateMetadataJob::dispatch($record, $request->pipelineOptions());

        return response()->json(['queued' => true], 202);
    }

    /**
     * Save the encoder's edits to a generation.
     */
    public function update(UpdateMetadataRequest $request, Record $record, RecordMetadata $metadata): JsonResponse
    {
        abort_unless($metadata->record_id === $record->id, 404);

        $metadata->payload = [...$metadata->payload, ...$request->validated()];
        $metadata->save();

        return response()->json(['id' => $metadata->id, 'payload' => $metadata->payload]);
    }

    /**
     * Make an earlier generation the active one again.
     */
    public function activate(Record $record, RecordMetadata $metadata): JsonResponse
    {
        Gate::authorize('update', $record);
        abort_unless($metadata->record_id === $record->id, 404);

        DB::transaction(function () use ($record, $metadata): void {
            $record->metadata()->where('is_active', true)->update(['is_active' => false]);
            $metadata->is_active = true;
            $metadata->save();
        });

        return response()->json(['id' => $metadata->id]);
    }
}
