<?php

namespace App\Services\Dashboard;

use App\Enums\FileRole;
use App\Enums\PipelineStage;
use App\Enums\ProcessingStatus;
use App\Enums\ReviewStatus;
use App\Enums\StageStatus;
use App\Models\Project;
use App\Models\Record;
use App\Models\RecordStage;
use App\Models\User;
use App\Services\Usage\ProcessingLimit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The encoder and admin workspace dashboard (`GET /dashboard`). Encoders see
 * their own records, admins every record, the same scope as the record
 * history. Each section is its own method so the page can lazily reload just
 * the parts that change while videos process.
 */
class WorkspaceDashboard
{
    private const int LIST_LIMIT = 5;

    private const int ACTIVITY_DAYS = 30;

    public function __construct(
        private User $user,
        private ProcessingLimit $processingLimit,
    ) {}

    /**
     * Counts by review and processing status from one grouped query, plus
     * this week's additions and the archived running time.
     *
     * @return array{total: int, added_this_week: int, duration_seconds: float, review: array<string, int>, processing: array<string, int>}
     */
    public function stats(): array
    {
        $review = array_fill_keys(array_map(fn (ReviewStatus $status): string => $status->value, ReviewStatus::cases()), 0);
        $processing = array_fill_keys(array_map(fn (ProcessingStatus $status): string => $status->value, ProcessingStatus::cases()), 0);

        $rows = $this->records()
            ->toBase()
            ->select(['review_status', 'processing_status', DB::raw('COUNT(*) as aggregate')])
            ->groupBy(['review_status', 'processing_status'])
            ->get();

        foreach ($rows as $row) {
            $review[$row->review_status] = ($review[$row->review_status] ?? 0) + (int) $row->aggregate;
            $processing[$row->processing_status] = ($processing[$row->processing_status] ?? 0) + (int) $row->aggregate;
        }

        $durationSeconds = (float) $this->records()
            ->join('record_files', fn ($join) => $join->on('record_files.record_id', '=', 'records.id')->where('record_files.role', FileRole::Master->value))
            ->join('record_technical_metadata', 'record_technical_metadata.record_file_id', '=', 'record_files.id')
            ->sum('record_technical_metadata.duration');

        return [
            'total' => array_sum($review),
            'added_this_week' => $this->records()->where('created_at', '>=', CarbonImmutable::now()->subDays(7))->count(),
            'duration_seconds' => round($durationSeconds, 1),
            'review' => $review,
            'processing' => $processing,
        ];
    }

    /**
     * Records added per day over the last 30 days, zeros included.
     *
     * @return list<array{date: string, count: int}>
     */
    public function activity(): array
    {
        $from = CarbonImmutable::today()->subDays(self::ACTIVITY_DAYS - 1);

        $counts = $this->records()
            ->toBase()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->where('created_at', '>=', $from)
            ->groupByRaw('DATE(created_at)')
            ->pluck('aggregate', 'day')
            ->mapWithKeys(fn (mixed $count, string $day): array => [substr($day, 0, 10) => (int) $count]);

        $days = [];

        for ($day = $from; $day->lte(CarbonImmutable::today()); $day = $day->addDay()) {
            $days[] = ['date' => $day->toDateString(), 'count' => $counts->get($day->toDateString(), 0)];
        }

        return $days;
    }

    /**
     * Records that need the encoder: processing failed, or an admin asked for
     * changes. Most recently changed first.
     *
     * @return list<array{id: string, title: string, reason: string, updated_at: string|null, creator: string|null}>
     */
    public function attention(): array
    {
        return $this->records()
            ->select(['id', 'created_by', 'title', 'processing_status', 'review_status', 'updated_at'])
            ->where(fn (Builder $query) => $query
                ->where('processing_status', ProcessingStatus::Failed)
                ->orWhere('review_status', ReviewStatus::ChangesRequested))
            ->with($this->creatorRelation())
            ->latest('updated_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (Record $record): array => [
                'id' => $record->id,
                'title' => $record->title,
                'reason' => $record->processing_status === ProcessingStatus::Failed ? 'failed' : 'changes_requested',
                'updated_at' => $record->updated_at?->toIso8601String(),
                'creator' => $this->creatorName($record),
            ])
            ->all();
    }

    /**
     * Queued and running records, oldest first, with the stage they are on
     * and an overall percentage across their pipeline stages.
     *
     * @return list<array{id: string, title: string, processing_status: string, stage: string|null, progress: int, created_at: string|null, creator: string|null}>
     */
    public function processing(): array
    {
        return $this->records()
            ->select(['id', 'created_by', 'title', 'processing_status', 'created_at'])
            ->whereIn('processing_status', [ProcessingStatus::Queued, ProcessingStatus::Processing])
            ->with(['stages:id,record_id,stage,status,progress', ...$this->creatorRelation()])
            ->oldest()
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(function (Record $record): array {
                $stages = $record->stages->reject(fn (RecordStage $stage): bool => $stage->status === StageStatus::Skipped);
                $running = $stages->first(fn (RecordStage $stage): bool => $stage->status === StageStatus::Running);
                $progress = $stages->isEmpty() ? 0 : (int) round($stages->avg(
                    fn (RecordStage $stage): int => $stage->status === StageStatus::Done ? 100 : max(0, min(100, $stage->progress)),
                ));

                return [
                    'id' => $record->id,
                    'title' => $record->title,
                    'processing_status' => $record->processing_status->value,
                    'stage' => $running?->stage instanceof PipelineStage ? $running->stage->label() : null,
                    'progress' => $progress,
                    'created_at' => $record->created_at?->toIso8601String(),
                    'creator' => $this->creatorName($record),
                ];
            })
            ->all();
    }

    /**
     * Processed drafts that still need reviewing and submitting, most
     * recently changed first.
     *
     * @return list<array{id: string, title: string, project: string|null, updated_at: string|null, creator: string|null}>
     */
    public function drafts(): array
    {
        return $this->records()
            ->select(['id', 'created_by', 'project_id', 'title', 'updated_at'])
            ->where('review_status', ReviewStatus::Draft)
            ->where('processing_status', ProcessingStatus::Ready)
            ->with(['project:id,title', ...$this->creatorRelation()])
            ->latest('updated_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (Record $record): array => [
                'id' => $record->id,
                'title' => $record->title,
                'project' => $record->project?->title,
                'updated_at' => $record->updated_at?->toIso8601String(),
                'creator' => $this->creatorName($record),
            ])
            ->all();
    }

    /**
     * Projects with records in scope, the most recently added to first.
     *
     * @return list<array{id: string, title: string, records_count: int, last_added_at: string|null}>
     */
    public function projects(): array
    {
        $scope = fn (Builder $query) => $this->scope($query);

        return Project::query()
            ->select(['id', 'title'])
            ->whereHas('records', $scope)
            ->withCount(['records' => $scope])
            ->withMax(['records' => $scope], 'created_at')
            ->orderByDesc('records_max_created_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'title' => $project->title,
                'records_count' => (int) $project->getAttribute('records_count'),
                'last_added_at' => $project->getAttribute('records_max_created_at') === null
                    ? null
                    : CarbonImmutable::parse($project->getAttribute('records_max_created_at'))->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Today's transcription minutes against the encoder's daily limit; null
     * for admins, who have no limit.
     *
     * @return array{limit: int, used: float, remaining: float}|null
     */
    public function allowance(): ?array
    {
        $status = $this->processingLimit->status($this->user);

        if ($status['limit'] === null) {
            return null;
        }

        return [
            'limit' => $status['limit'],
            'used' => $status['used'],
            'remaining' => (float) $status['remaining'],
        ];
    }

    /**
     * @return Builder<Record>
     */
    private function records(): Builder
    {
        return $this->scope(Record::query());
    }

    /**
     * Encoders see only their own records; admins see everything.
     *
     * @param  Builder<Record>  $query
     * @return Builder<Record>
     */
    private function scope(Builder $query): Builder
    {
        return $query->when(! $this->user->isAdmin(), fn (Builder $own) => $own->where('records.created_by', $this->user->id));
    }

    /**
     * Admins see who added each record; encoders only ever see their own.
     *
     * @return list<string>
     */
    private function creatorRelation(): array
    {
        return $this->user->isAdmin() ? ['creator:id,name'] : [];
    }

    private function creatorName(Record $record): ?string
    {
        return $this->user->isAdmin() ? $record->creator?->name : null;
    }
}
