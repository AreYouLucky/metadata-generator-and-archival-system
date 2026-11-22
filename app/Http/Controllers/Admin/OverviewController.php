<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProcessingStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Record;
use App\Services\Catalogue\Distribution;
use App\Services\Usage\UsageReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin overview (PROJECT_SPEC §8 `GET /admin`): pending approvals, the
 * processing queue and usage this month.
 */
class OverviewController extends Controller
{
    private const int LIST_LIMIT = 5;

    public function __invoke(): Response
    {
        Gate::authorize('viewAny', Record::class);

        $today = CarbonImmutable::today();
        $usage = UsageReport::between($today->startOfMonth(), $today);

        return Inertia::render('admin/overview-page/overview', [
            'pending' => [
                'count' => Record::query()->where('review_status', ReviewStatus::Submitted)->count(),
                'oldest' => Record::query()
                    ->select(['id', 'title', 'created_by', 'submitted_at'])
                    ->where('review_status', ReviewStatus::Submitted)
                    ->with('creator:id,name')
                    ->orderBy('submitted_at')
                    ->limit(self::LIST_LIMIT)
                    ->get()
                    ->map(fn (Record $record): array => [
                        'id' => $record->id,
                        'title' => $record->title,
                        'creator' => $record->creator?->name,
                        'submitted_at' => $record->submitted_at?->toIso8601String(),
                    ])
                    ->all(),
            ],
            'processing' => [
                'queued' => Record::query()->where('processing_status', ProcessingStatus::Queued)->count(),
                'running' => Record::query()->where('processing_status', ProcessingStatus::Processing)->count(),
                'failed' => Record::query()->where('processing_status', ProcessingStatus::Failed)->count(),
                'active' => Record::query()
                    ->select(['id', 'title', 'created_by', 'processing_status', 'created_at'])
                    ->whereIn('processing_status', [ProcessingStatus::Queued, ProcessingStatus::Processing])
                    ->with('creator:id,name')
                    ->oldest()
                    ->limit(self::LIST_LIMIT)
                    ->get()
                    ->map(fn (Record $record): array => [
                        'id' => $record->id,
                        'title' => $record->title,
                        'creator' => $record->creator?->name,
                        'processing_status' => $record->processing_status->value,
                        'created_at' => $record->created_at?->toIso8601String(),
                    ])
                    ->all(),
            ],
            'published' => Distribution::constrain(Record::query())->count(),
            'usage' => [
                'from' => $today->startOfMonth()->toDateString(),
                'to' => $today->toDateString(),
                'totals' => $usage->totals(),
                'top_encoders' => array_slice($usage->byEncoder(), 0, self::LIST_LIMIT),
            ],
        ]);
    }
}
