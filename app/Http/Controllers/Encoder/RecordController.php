<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\PipelineStage;
use App\Enums\Preset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\CreateRecordRequest;
use App\Http\Requests\Encoder\IndexRecordsRequest;
use App\Http\Requests\Encoder\StoreRecordRequest;
use App\Models\Project;
use App\Models\ProjectSeries;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordMetadata;
use App\Models\RecordSegment;
use App\Models\RecordStage;
use App\Models\User;
use App\Services\Records\PipelineOptions;
use App\Services\Records\RecordIngestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RecordController extends Controller
{
    private const int PER_PAGE = 15;

    /**
     * Record history: an encoder's own records; every record for admins.
     */
    public function index(IndexRecordsRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();

        $records = Record::query()
            ->select(['id', 'created_by', 'project_id', 'project_series_id', 'title', 'processing_status', 'review_status', 'created_at'])
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->where('created_by', $user->id))
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->whereLike('title', '%'.addcslashes($search, '%_\\').'%'))
            ->when($filters['processing_status'] ?? null, fn (Builder $query, string $status) => $query->where('processing_status', $status))
            ->when($filters['review_status'] ?? null, fn (Builder $query, string $status) => $query->where('review_status', $status))
            ->when(
                $filters['sort'] ?? 'newest',
                fn (Builder $query, string $sort) => match ($sort) {
                    'oldest' => $query->oldest(),
                    'title' => $query->orderBy('title'),
                    default => $query->latest(),
                },
            )
            ->with([
                'masterFile:id,record_id,role',
                'masterFile.technicalMetadata:id,record_file_id,duration',
                'project:id,title',
                'projectSeries:id,title',
                ...($user->isAdmin() ? ['creator:id,name'] : []),
            ])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Record $record): array => [
                'id' => $record->id,
                'title' => $record->title,
                'processing_status' => $record->processing_status->value,
                'review_status' => $record->review_status->value,
                'duration' => $record->masterFile?->technicalMetadata?->duration,
                'project' => $record->project?->title,
                'series' => $record->projectSeries?->title,
                'created_at' => $record->created_at?->toIso8601String(),
                'creator' => $user->isAdmin() ? $record->creator?->name : null,
            ]);

        return Inertia::render('encoder/dashboard-page/dashboard', [
            'records' => $records,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'processing_status' => $filters['processing_status'] ?? null,
                'review_status' => $filters['review_status'] ?? null,
                'sort' => $filters['sort'] ?? 'newest',
            ],
            'showCreator' => $user->isAdmin(),
        ]);
    }

    public function create(CreateRecordRequest $request): Response
    {
        $project = $request->validated('project')
            ? Project::with(['series' => fn (HasMany $query) => $query->select(['id', 'project_id', 'title', 'date', 'end_date'])->orderBy('date')->orderBy('title')])
                ->whereKey((string) $request->validated('project'))
                ->first(['id', 'title', 'date', 'end_date', 'location', 'region_id', 'prov_id', 'city_id', 'brgy_id', 'agency_id'])
            : null;

        return Inertia::render('encoder/records/create-page/create', [
            'initialProject' => $project === null ? null : [
                'id' => $project->id,
                'title' => $project->title,
                'date' => $project->date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'agency' => $project->agency()->first(['id', 'name', 'acronym'])?->summary(),
                'location' => $project->location,
                'place' => $project->loadMissing(['barangay:id,name', 'city:id,name', 'province:id,name', 'region:id,name'])->placeLabel(),
                'series' => $project->series->map(fn (ProjectSeries $series): array => [
                    'id' => $series->id,
                    'title' => $series->title,
                    'date' => $series->date?->toDateString(),
                    'end_date' => $series->end_date?->toDateString(),
                ])->values()->all(),
            ],
            'initialSeriesId' => $project === null ? null : $request->validated('series'),
            'presets' => $this->presets(),
            'limits' => [
                'max_upload_mb' => config('archive.max_upload_mb'),
                'max_video_minutes' => config('archive.max_video_minutes'),
            ],
        ]);
    }

    /**
     * Turn a finished chunked upload into a record and start processing.
     */
    public function store(StoreRecordRequest $request, RecordIngestService $ingest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $record = $ingest->ingest(
            $user,
            $request->validated('upload_id'),
            $request->safe()->only(['title', 'project_id', 'project_series_id', 'agency_id', 'division_id', 'expected_md5', 'expected_sha256']),
            $request->pipelineOptions(),
        );

        return response()->json([
            'id' => $record->id,
            'url' => route('records.show', $record),
        ], 201);
    }

    /**
     * The workspace. Props are closures so partial reloads (after a stage
     * completes) only query what they ask for.
     */
    public function show(Record $record): Response
    {
        Gate::authorize('view', $record);

        $user = request()->user();

        return Inertia::render('encoder/records/show-page/show', [
            'record' => fn (): array => [
                'id' => $record->id,
                'title' => $record->title,
                'project' => $record->project()->first(['id', 'title'])?->only(['id', 'title']),
                'series' => $record->projectSeries()->value('title'),
                'agency' => $record->agency()->first(['id', 'name', 'acronym'])?->summary(),
                'division' => $record->division()->value('name'),
                'language' => $record->language,
                'language_confidence' => $record->language_confidence,
                'processing_status' => $record->processing_status->value,
                'processing_error' => $record->processing_error,
                'review_status' => $record->review_status->value,
                'created_at' => $record->created_at?->toIso8601String(),
                'can_edit' => $user?->can('update', $record) ?? false,
            ],
            'media' => fn (): array => $this->media($record),
            'stages' => fn (): array => $this->stages($record),
            'transcript' => fn (): ?array => $this->transcript($record),
            'metadata' => fn (): ?array => $this->activeMetadata($record),
            'metadataHistory' => fn (): array => $record->metadata()
                ->latest()
                ->limit(20)
                ->get(['id', 'preset', 'model', 'custom_prompt', 'is_active', 'created_at'])
                ->map(fn (RecordMetadata $metadata): array => [
                    'id' => $metadata->id,
                    'preset' => $metadata->preset->value,
                    'model' => $metadata->model,
                    'custom_prompt' => $metadata->custom_prompt,
                    'is_active' => $metadata->is_active,
                    'created_at' => $metadata->created_at?->toIso8601String(),
                ])
                ->all(),
            'presets' => $this->presets(),
        ]);
    }

    /**
     * @return array{url: string, mime_type: string|null, playable: bool|null, duration: float|null, has_waveform: bool}
     */
    private function media(Record $record): array
    {
        /** @var RecordFile|null $master */
        $master = $record->masterFile()->with('technicalMetadata')->first();
        $technical = $master?->technicalMetadata;
        $hasWaveform = Storage::disk('media')->exists(RecordWaveformController::path($record));

        return [
            'url' => route('records.media', $record),
            'mime_type' => $master?->mime_type,
            'playable' => $technical?->isBrowserPlayable($master->mime_type),
            'duration' => $technical?->duration,
            'has_waveform' => $hasWaveform,
        ];
    }

    /**
     * @return list<array{stage: string, label: string, status: string, progress: int, message: string|null}>
     */
    private function stages(Record $record): array
    {
        $rows = $record->stages()->get(['stage', 'status', 'progress', 'message'])->keyBy(fn (RecordStage $stage): string => $stage->stage->value);

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
        }, PipelineStage::active())));
    }

    /**
     * @return array{language: string, segments: list<array{id: int, start: float, end: float, text: string, is_edited: bool}>}|null
     */
    private function transcript(Record $record): ?array
    {
        $transcript = $record->originalTranscript()->first(['id', 'record_id', 'language']);

        if ($transcript === null) {
            return null;
        }

        return [
            'language' => $transcript->language,
            'segments' => array_values($transcript->segments()
                ->get(['id', 'start', 'end', 'text', 'is_edited'])
                ->map(fn (RecordSegment $segment): array => [
                    'id' => $segment->id,
                    'start' => $segment->start,
                    'end' => $segment->end,
                    'text' => $segment->text,
                    'is_edited' => $segment->is_edited,
                ])
                ->all()),
        ];
    }

    /**
     * @return array{id: int, preset: string, model: string, custom_prompt: string|null, created_at: string|null, payload: array<string, mixed>}|null
     */
    private function activeMetadata(Record $record): ?array
    {
        $metadata = $record->activeMetadata()->first();

        return $metadata === null ? null : [
            'id' => $metadata->id,
            'preset' => $metadata->preset->value,
            'model' => $metadata->model,
            'custom_prompt' => $metadata->custom_prompt,
            'created_at' => $metadata->created_at?->toIso8601String(),
            'payload' => $metadata->payload,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presets(): array
    {
        return array_map(
            fn (string $name): array => (new PipelineOptions(Preset::from($name)))->presetRules(),
            PipelineOptions::enabledPresets(),
        );
    }
}
