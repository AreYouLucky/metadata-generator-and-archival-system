<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\IndexReviewsRequest;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pending reviews: an encoder's records waiting for an admin's decision,
 * oldest first. Admins review in their own queue (/admin/approvals), so they
 * are sent there.
 */
class ReviewController extends Controller
{
    private const int PER_PAGE = 20;

    public function __invoke(IndexReviewsRequest $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdmin()) {
            return to_route('admin.approvals.index');
        }

        $search = $request->validated('q');

        $records = Record::query()
            ->select(['id', 'created_by', 'project_id', 'project_series_id', 'title', 'processing_status', 'submitted_at'])
            ->where('review_status', ReviewStatus::Submitted)
            ->where('created_by', $user->id)
            ->when($search, fn (Builder $query, string $q) => $query->whereLike('title', '%'.addcslashes($q, '%_\\').'%'))
            ->with(['project:id,title', 'projectSeries:id,title'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Record $record): array => [
                'id' => $record->id,
                'title' => $record->title,
                'project' => $record->project?->title,
                'series' => $record->projectSeries?->title,
                'creator' => null,
                'processing_status' => $record->processing_status->value,
                'submitted_at' => $record->submitted_at?->toIso8601String(),
            ]);

        return Inertia::render('encoder/reviews-page/reviews', [
            'records' => $records,
            'filters' => ['q' => $search ?? ''],
            'showCreator' => false,
        ]);
    }
}
