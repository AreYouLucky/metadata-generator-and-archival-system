<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Enums\ContributorRole;
use App\Enums\ExportFormat;
use App\Enums\FileRole;
use App\Enums\PipelineStage;
use App\Enums\Preset;
use App\Enums\SecurityClassification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\CreateRecordRequest;
use App\Http\Requests\Encoder\DestroyRecordRequest;
use App\Http\Requests\Encoder\IndexRecordsRequest;
use App\Http\Requests\Encoder\StoreRecordRequest;
use App\Http\Requests\Encoder\StoreTranslationRequest;
use App\Http\Requests\Encoder\UpdateRecordRequest;
use App\Models\Project;
use App\Models\ProjectSeries;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordMetadata;
use App\Models\RecordStage;
use App\Models\User;
use App\Models\VideoAuditLog;
use App\Services\Records\ArchivePresenter;
use App\Services\Records\AudioExports;
use App\Services\Records\AuditTrail;
use App\Services\Records\PipelineOptions;
use App\Services\Records\ProcessingCanceller;
use App\Services\Records\RecordContent;
use App\Services\Records\RecordIngestService;
use App\Services\Records\ReviewHistory;
use App\Services\Records\VisualsPresenter;
use App\Services\Usage\ProcessingLimit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
            ->select(['id', 'created_by', 'project_id', 'project_series_id', 'title', 'accession_number', 'security_classification', 'processing_status', 'review_status', 'created_at'])
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->where('created_by', $user->id))
            // Title or accession number (PROJECT_SPEC §13, Phase 3).
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $match) => $match->whereLike('title', $pattern)->orWhereLike('accession_number', $pattern));
            })
            ->when(($filters['flag'] ?? null) === 'embargoed', fn (Builder $query) => $query->whereHas('rights', fn (Builder $rights) => $rights->where('embargo_until', '>', now())))
            ->when(($filters['flag'] ?? null) === 'classified', fn (Builder $query) => $query->where('security_classification', '!=', SecurityClassification::Unclassified))
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
                'rights:id,record_id,embargo_until',
                ...($user->isAdmin() ? ['creator:id,name'] : []),
            ])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Record $record): array => [
                'id' => $record->id,
                'title' => $record->title,
                'accession_number' => $record->accession_number,
                'classification' => $record->security_classification->value,
                'embargo_until' => $record->rights?->isEmbargoed() ? $record->rights->embargo_until?->toDateString() : null,
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
                'flag' => $filters['flag'] ?? null,
                'sort' => $filters['sort'] ?? 'newest',
            ],
            'showCreator' => $user->isAdmin(),
        ]);
    }

    public function create(CreateRecordRequest $request, ProcessingLimit $limit): Response
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
            'dailyLimit' => $request->user() instanceof User ? $limit->status($request->user()) : null,
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
                'accession_number' => $record->accession_number,
                'classification' => $record->security_classification->value,
                // Only while the embargo is in force (EmbargoBadge).
                'embargo_until' => $record->rights?->isEmbargoed() ? $record->rights->embargo_until?->toDateString() : null,
                'created_at' => $record->created_at?->toIso8601String(),
                'can_edit' => $user?->can('update', $record) ?? false,
                // Permission only: the page shows Cancel while its live status is queued or processing.
                'can_cancel' => $user?->can('cancel', $record) ?? false,
                'can_delete' => $user?->can('delete', $record) ?? false,
            ],
            'media' => fn (): array => $this->media($record),
            'stages' => fn (): array => $this->stages($record),
            'transcript' => fn (): ?array => app(RecordContent::class)->transcript($record),
            'contributors' => fn (): array => ContributorController::list($record),
            'contributorRoles' => ContributorRole::options(),
            'speakers' => fn (): array => app(RecordContent::class)->speakers($record),
            'metadata' => fn (): ?array => app(RecordContent::class)->activeMetadata($record),
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
            'translations' => fn (): array => TranslationController::list($record),
            'translationLanguages' => array_map(
                fn (string $code, string $name): array => ['code' => $code, 'name' => $name],
                array_keys(StoreTranslationRequest::languages()),
                array_values(StoreTranslationRequest::languages()),
            ),
            'exportFormats' => ExportFormat::options(),
            'audioExports' => fn (): array => app(AudioExports::class)->present($record),
            'exportSchemas' => fn (): array => $user instanceof User ? ExportSchemaController::list($user) : [],
            // Approval workflow (Phase 4): notes from reviews and what this user may do next.
            'review' => fn (): array => [
                'history' => app(ReviewHistory::class)->for($record),
                'can_submit' => $user?->can('submit', $record) ?? false,
                'can_review' => $user?->can('review', $record) ?? false,
                'can_reopen' => $user?->can('reopen', $record) ?? false,
                'can_view_access_log' => $user?->can('viewAccessLog', $record) ?? false,
                'is_public' => Gate::forUser(null)->allows('distribute', $record),
            ],
            // Visual analysis (Phase 5): frames, shots, scenes and the thumbnail.
            'visuals' => fn (): array => app(VisualsPresenter::class)->present($record),
            'archive' => fn (): array => app(ArchivePresenter::class)->present($record),
            'archiveOptions' => fn (): array => $user instanceof User ? app(ArchivePresenter::class)->options($user) : [],
        ]);
    }

    /**
     * Save one Archive sub-tab's catalogue fields. Each change is logged as
     * `updated` with the names of the fields that changed.
     */
    public function update(UpdateRecordRequest $request, Record $record, ArchivePresenter $archive): JsonResponse
    {
        $data = $request->validated();

        // Workflow field: set explicitly, never mass-assigned (PROJECT_SPEC §8).
        if (array_key_exists('accession_number', $data)) {
            $record->accession_number = (string) $data['accession_number'];
            unset($data['accession_number']);
        }

        $record->fill($data);
        $changed = array_keys($record->getDirty());

        if ($changed !== []) {
            $record->save();

            VideoAuditLog::create([
                'record_id' => $record->id,
                'record_file_id' => null,
                'action' => AuditAction::Updated,
                'performed_by' => $request->user()?->id,
                'performed_at' => now(),
                'remarks' => 'Changed: '.implode(', ', $changed),
            ]);
        }

        return response()->json(['archive' => $archive->present($record->refresh())]);
    }

    /**
     * Soft delete: the record leaves every list and the public site, but its
     * row, files and audit log stay (PROJECT_SPEC §8; files are purged later
     * by the cleanup job). Processing still running is cancelled first.
     */
    public function destroy(DestroyRecordRequest $request, Record $record, ProcessingCanceller $canceller, AuditTrail $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $canceller->cancel($record, $user);

        DB::transaction(function () use ($record, $user, $audit): void {
            $audit->log($record, AuditAction::Deleted, $user, 'Record deleted.');
            $record->delete();
        });

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array{url: string, mime_type: string|null, playable: bool|null, source: string, frame_rate: string|null, start_timecode: string|null, duration: float|null, has_waveform: bool}
     */
    private function media(Record $record): array
    {
        /** @var RecordFile|null $master */
        $master = $record->masterFile()->with('technicalMetadata')->first();
        $technical = $master?->technicalMetadata;
        $hasWaveform = Storage::disk('media')->exists(RecordWaveformController::path($record));
        $masterPlays = $technical?->isBrowserPlayable($master->mime_type);
        $hasProxy = $record->files()->where('role', FileRole::Proxy)->exists();
        // `version` changes when the access copy arrives, so the player reloads.
        $source = $masterPlays === false && $hasProxy ? 'proxy' : 'master';

        return [
            'url' => route('records.media', [$record, 'v' => $source]),
            'mime_type' => $master?->mime_type,
            'playable' => $masterPlays === null ? null : ($masterPlays || $hasProxy),
            'source' => $source,
            'frame_rate' => $technical?->frame_rate,
            'start_timecode' => $technical?->start_timecode,
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
