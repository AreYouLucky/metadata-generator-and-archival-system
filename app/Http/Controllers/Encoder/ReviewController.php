<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\IndexReviewsRequest;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pending reviews: records submitted for approval, oldest first. Encoders see
 * their own; admins see everyone's. Approving and rejecting arrive with the
 * approval workflow (Phase 3); until then the queue links to the workspace.
 */
class ReviewController extends Controller
{
    private const int PER_PAGE = 20;

    public function __invoke(IndexReviewsRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $search = $request->validated('q');

        $records = Record::query()
            ->select(['id', 'created_by', 'project_id', 'project_series_id', 'title', 'processing_status', 'submitted_at'])
            ->where('review_status', ReviewStatus::Submitted)
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->where('created_by', $user->id))
            ->when($search, fn (Builder $query, string $q) => $query->whereLike('title', '%'.addcslashes($q, '%_\\').'%'))
            ->with(['project:id,title', 'projectSeries:id,title', 'creator:id,name'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Record $record): array => [
                'id' => $record->id,
                'title' => $record->title,
                'project' => $record->project?->title,
                'series' => $record->projectSeries?->title,
                'creator' => $user->isAdmin() ? $record->creator?->name : null,
                'processing_status' => $record->processing_status->value,
                'submitted_at' => $record->submitted_at?->toIso8601String(),
            ]);

        return Inertia::render('encoder/reviews-page/reviews', [
            'records' => $records,
            'filters' => ['q' => $search ?? ''],
            'showCreator' => $user->isAdmin(),
        ]);
    }
}
