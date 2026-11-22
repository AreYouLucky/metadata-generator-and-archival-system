<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\PipelineStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\StoreBatchRequest;
use App\Models\Batch;
use App\Models\Record;
use App\Models\RecordStage;
use App\Models\User;
use App\Services\Batches\BatchArchive;
use App\Services\Records\RecordIngestService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BatchController extends Controller
{
    /**
     * Several uploads and web addresses become one batch of records, each
     * processed on its own (PROJECT_SPEC §8, POST /batches).
     */
    public function store(StoreBatchRequest $request, RecordIngestService $ingest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $batch = $ingest->ingestBatch(
            $user,
            $request->validated('name'),
            $request->items(),
            $request->filing(),
            $request->pipelineOptions(),
        );

        return response()->json([
            'id' => $batch->id,
            'url' => route('batches.show', $batch),
        ], 201);
    }

    /**
     * The batch page: each record's progress (live over Reverb), and the ZIP.
     * A batch holds at most MAX_BATCH_SIZE records, so the list isn't paged.
     */
    public function show(Batch $batch, BatchArchive $archive): Response
    {
        Gate::authorize('view', $batch);

        $records = $batch->records()
            ->select(['id', 'batch_id', 'title', 'accession_number', 'source_url', 'security_classification', 'processing_status', 'processing_error', 'review_status', 'created_at'])
            ->with([
                'stages' => fn (HasMany $query) => $query->select(['id', 'record_id', 'stage', 'status', 'progress', 'message']),
                'rights:id,record_id,access_level,permission_status,embargo_until',
            ])
            ->orderBy('created_at')
            ->get();

        return Inertia::render('encoder/batches/show-page/show', [
            'batch' => [
                'id' => $batch->id,
                'name' => $batch->label(),
                'status' => $batch->status->value,
                'total' => $batch->total,
                'completed' => $batch->completed,
                'failed' => $batch->failed,
                'created_at' => $batch->created_at?->toIso8601String(),
                'zip_status' => $batch->zip_status?->value,
                'zip_built_at' => $batch->zip_built_at?->toIso8601String(),
            ],
            'records' => $records->map(fn (Record $record): array => [
                'id' => $record->id,
                'title' => $record->title,
                'accession_number' => $record->accession_number,
                'source_host' => $record->source_url !== null ? parse_url($record->source_url, PHP_URL_HOST) : null,
                'processing_status' => $record->processing_status->value,
                'processing_error' => $record->processing_error,
                'review_status' => $record->review_status->value,
                'stages' => self::stages($record),
                'zip_exclusions' => $archive->exclusions($record),
            ])->values()->all(),
        ]);
    }

    /**
     * @return list<array{stage: string, label: string, status: string, progress: int, message: string|null}>
     */
    private static function stages(Record $record): array
    {
        $rows = $record->stages->keyBy(fn (RecordStage $stage): string => $stage->stage->value);

        return array_values(array_filter(array_map(function (PipelineStage $stage) use ($rows): ?array {
            /** @var RecordStage|null $row */
            $row = $rows->get($stage->value);

            return $row === null ? null : [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'status' => $row->status->value,
                'progress' => $row->progress,
                'message' => $row->message,
            ];
        }, PipelineStage::tracked())));
    }
}
