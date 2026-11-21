<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\SearchProjectsRequest;
use App\Models\Project;
use App\Models\ProjectSeries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;

class ProjectOptionsController extends Controller
{
    private const int LIMIT = 20;

    /**
     * Projects (with their series) for the Import media picker: the 20 most
     * recent, or the 20 best title matches.
     */
    public function __invoke(SearchProjectsRequest $request): JsonResponse
    {
        $search = $request->validated('q');

        $projects = Project::query()
            ->select(['id', 'title', 'date', 'end_date', 'location', 'region_id', 'prov_id', 'city_id', 'brgy_id', 'agency_id'])
            ->withPlace()
            ->with('agency:id,name,acronym')
            ->when($search, fn (Builder $query, string $q) => $query->whereLike('title', '%'.addcslashes($q, '%_\\').'%'))
            ->with(['series' => fn (HasMany $query) => $query
                ->select(['id', 'project_id', 'title', 'date', 'end_date'])
                ->orderBy('date')
                ->orderBy('title')])
            ->orderByDesc('date')
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'title' => $project->title,
                'date' => $project->date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'location' => $project->location,
                'place' => $project->placeLabel(),
                'agency' => $project->agency?->summary(),
                'series' => $project->series->map(fn (ProjectSeries $series): array => [
                    'id' => $series->id,
                    'title' => $series->title,
                    'date' => $series->date?->toDateString(),
                    'end_date' => $series->end_date?->toDateString(),
                ])->values()->all(),
            ]);

        return response()->json(['data' => $projects]);
    }
}
