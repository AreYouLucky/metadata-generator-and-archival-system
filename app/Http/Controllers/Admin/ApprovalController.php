<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewAction;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Encoder\RecordMediaController;
use App\Http\Controllers\Encoder\TranslationController;
use App\Http\Requests\Admin\IndexApprovalsRequest;
use App\Http\Requests\Admin\ReviewRecordRequest;
use App\Models\Record;
use App\Models\RecordContributor;
use App\Models\RecordKeyword;
use App\Models\RecordSubject;
use App\Models\User;
use App\Services\Catalogue\Distribution;
use App\Services\Records\ArchivePresenter;
use App\Services\Records\RecordContent;
use App\Services\Records\ReviewHistory;
use App\Services\Records\ReviewWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin review queue (PROJECT_SPEC §8, `/admin/approvals`): submitted
 * records oldest first; a review page with the video and the catalogue
 * record; approve, request changes or reject with notes.
 */
class ApprovalController extends Controller
{
    private const int PER_PAGE = 20;

    public function index(IndexApprovalsRequest $request): Response
    {
        $search = $request->validated('q');

        $records = Record::query()
            ->select(['id', 'created_by', 'project_id', 'project_series_id', 'title', 'accession_number', 'submitted_at'])
            ->where('review_status', ReviewStatus::Submitted)
            ->when($search, function (Builder $query, string $q): void {
                $pattern = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn (Builder $match) => $match->whereLike('title', $pattern)->orWhereLike('accession_number', $pattern));
            })
            ->with(['project:id,title', 'projectSeries:id,title', 'creator:id,name', 'masterFile:id,record_id,role', 'masterFile.technicalMetadata:id,record_file_id,duration'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Record $record): array => [
                'id' => $record->id,
                'title' => $record->title,
                'accession_number' => $record->accession_number,
                'project' => $record->project?->title,
                'series' => $record->projectSeries?->title,
                'creator' => $record->creator?->name,
                'duration' => $record->masterFile?->technicalMetadata?->duration,
                'submitted_at' => $record->submitted_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/approvals/index-page/index', [
            'records' => $records,
            'filters' => ['q' => $search ?? ''],
        ]);
    }

    /**
     * The review page: the video and, read-only, everything from the
     * workspace (transcript, AI metadata, translations, the archive record).
     * Works for any record an admin opens, but the decision buttons only
     * appear while it is submitted.
     */
    public function show(Record $record, ReviewHistory $history, Distribution $distribution, RecordContent $content, ArchivePresenter $archive): Response
    {
        Gate::authorize('view', $record);

        $record->load([
            'project:id,title',
            'projectSeries:id,title',
            'agency:id,name,acronym',
            'division:id,name',
            'genre:id,name',
            'videoType:id,name',
            'creator:id,name',
            'rights',
            'contributors:id,record_id,role,name,sort_order',
            'subjects:id,record_id,subject,type,source,is_confirmed',
            'keywords:id,record_id,keyword,source,is_confirmed',
            'masterFile:id,record_id,role',
            'masterFile.technicalMetadata:id,record_file_id,duration,resolution',
            'activeMetadata:id,record_id,payload,is_active',
        ])->loadMissing(['barangay:id,name', 'city:id,name', 'province:id,name', 'region:id,name']);

        $rights = $record->rights;
        /** @var array<string, mixed> $payload */
        $payload = $record->activeMetadata->payload ?? [];

        return Inertia::render('admin/approvals/show-page/show', [
            'record' => [
                'id' => $record->id,
                'title' => $record->title,
                'alternative_title' => $record->alternative_title,
                'description' => $record->description,
                'summary' => is_string($payload['summary'] ?? null) ? $payload['summary'] : null,
                'accession_number' => $record->accession_number,
                'project' => $record->project?->title,
                'series' => $record->projectSeries?->title,
                'agency' => $record->agency?->name,
                'division' => $record->division?->name,
                'genre' => $record->genre?->name,
                'video_type' => $record->videoType?->name,
                'language' => $record->language,
                'language_confidence' => $record->language_confidence,
                'date_recorded' => $record->date_recorded?->toDateString(),
                'date_recorded_precision' => $record->date_recorded_precision?->value,
                'place' => $record->placeLabel(),
                'location' => $record->location,
                'classification' => $record->security_classification->value,
                'creator' => $record->creator?->name,
                'review_status' => $record->review_status->value,
                'processing_status' => $record->processing_status->value,
                'submitted_at' => $record->submitted_at?->toIso8601String(),
                'duration' => $record->masterFile?->technicalMetadata?->duration,
                'resolution' => $record->masterFile?->technicalMetadata?->resolution,
                'contributors' => $record->contributors->map(fn (RecordContributor $contributor): array => [
                    'role' => $contributor->role->label(),
                    'name' => $contributor->name,
                ])->values()->all(),
                'subjects' => $record->subjects->where('is_confirmed', true)->map(fn (RecordSubject $subject): string => $subject->subject)->values()->all(),
                'keywords' => $record->keywords->where('is_confirmed', true)->map(fn (RecordKeyword $keyword): string => $keyword->keyword)->values()->all(),
                'pending_suggestions' => $record->subjects->where('is_confirmed', false)->count() + $record->keywords->where('is_confirmed', false)->count(),
                'rights' => $rights === null ? null : [
                    'copyright_holder' => $rights->copyright_holder,
                    'license' => $rights->license,
                    'access_level' => $rights->access_level->value,
                    'permission_status' => $rights->permission_status->value,
                    'usage_restrictions' => $rights->usage_restrictions,
                    'embargo_until' => $rights->embargo_until?->toDateString(),
                ],
            ],
            'media' => [
                'url' => route('records.media', $record),
                'playable' => RecordMediaController::playableFile($record) !== null,
            ],
            // Everything the encoder worked on in the workspace, read-only.
            'transcript' => $content->transcript($record),
            'speakers' => $content->speakers($record),
            'metadata' => $content->activeMetadata($record),
            'translations' => TranslationController::list($record),
            'archive' => $archive->present($record),
            // What would still keep it off the public site once approved.
            'publicBlockers' => $distribution->blockers($record, ignoreApproval: true),
            'history' => $history->for($record),
            'canReview' => request()->user()?->can('review', $record) ?? false,
            'canReopen' => request()->user()?->can('reopen', $record) ?? false,
        ]);
    }

    public function store(ReviewRecordRequest $request, Record $record, ReviewWorkflow $workflow): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $action = $request->action();

        abort_unless(
            $workflow->review($record, $admin, $action, $request->validated('notes')),
            409,
            'This record is no longer waiting for review.',
        );

        $message = match ($action) {
            ReviewAction::Approve => 'Approved. It is now published to the catalogue if its rights allow.',
            ReviewAction::RequestChanges => 'Sent back to the encoder with your notes.',
            ReviewAction::Reject => 'Rejected.',
        };

        return response()->json(['review_status' => $record->review_status->value, 'message' => $message]);
    }
}
