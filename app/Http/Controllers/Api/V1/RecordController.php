<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRecordsRequest;
use App\Http\Requests\Api\V1\ShowRecordRequest;
use App\Http\Requests\Api\V1\StoreRecordRequest;
use App\Http\Resources\Api\V1\RecordResource;
use App\Http\Resources\Api\V1\RecordStatusResource;
use App\Models\Record;
use App\Models\User;
use App\Services\Records\RecordIngestService;
use App\Services\Uploads\ChunkedUploadStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Public API (Phase 7, /api/v1): list, create and follow records with a
 * Sanctum token. A token acts with its owner's permissions (encoders see
 * their own records, admins all of them), narrowed to its abilities.
 */
class RecordController extends Controller
{
    public function index(IndexRecordsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();

        $records = Record::query()
            ->select(['id', 'created_by', 'title', 'accession_number', 'processing_status', 'review_status', 'created_at', 'updated_at'])
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->where('created_by', $user->id))
            ->when($filters['processing_status'] ?? null, fn (Builder $query, string $status) => $query->where('processing_status', $status))
            ->when($filters['review_status'] ?? null, fn (Builder $query, string $status) => $query->where('review_status', $status))
            ->when($filters['updated_since'] ?? null, fn (Builder $query, string $since) => $query->where('updated_at', '>=', $since))
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? config('archive.api.per_page')))
            ->withQueryString();

        return RecordResource::collection($records);
    }

    /**
     * A file sent in the request goes through the same checked ingest as an
     * Import page upload (real type, permanent place, checksums); a web
     * address is downloaded by the AI service first.
     */
    public function store(StoreRecordRequest $request, RecordIngestService $ingest, ChunkedUploadStore $uploads): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $url = $request->validated('url');
        $file = $request->videoFile();
        $uploadId = null;

        if (! is_string($url)) {
            abort_if($file === null, 422, 'Send a video file or a web address (url).');

            $name = $request->safeFilename($file);
            $uploadId = $uploads->start($user, $name, strtolower(pathinfo($name, PATHINFO_EXTENSION)), (int) $file->getSize())['id'];
            $uploads->append($uploadId, $user, 0, $file);
        }

        // One transaction, so processing (queued after commit) never starts before the webhook is saved.
        $record = DB::transaction(function () use ($request, $ingest, $user, $url, $uploadId): Record {
            $record = is_string($url)
                ? $ingest->import($user, $url, [...$request->filing(), 'title' => $request->validated('title')], $request->pipelineOptions())
                : $ingest->ingest(
                    $user,
                    (string) $uploadId,
                    $request->safe()->only(['title', 'project_id', 'project_series_id', 'agency_id', 'division_id', 'expected_md5', 'expected_sha256']),
                    $request->pipelineOptions(),
                );

            $webhook = $request->validated('webhook_url');

            if (is_string($webhook)) {
                // Workflow field: set explicitly, never mass-assigned.
                $record->webhook_url = $webhook;
                $record->save();
            }

            return $record;
        });

        return (new RecordStatusResource($record->refresh()))->response()->setStatusCode(201);
    }

    public function show(ShowRecordRequest $request, Record $record): RecordStatusResource
    {
        return new RecordStatusResource($record);
    }
}
