<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\IndexProjectsRequest;
use App\Http\Requests\Encoder\StoreProjectRequest;
use App\Http\Requests\Encoder\UpdateProjectRequest;
use App\Models\Project;
use App\Models\ProjectSeries;
use App\Models\Record;
use App\Models\User;
use App\Services\Projects\ProjectThumbnailStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Projects (events or programmes) that group records. Every upload is filed
 * under one (owner's decision, see DECISIONS.md). Anyone may file under any
 * project; only its creator and admins may edit it.
 */
class ProjectController extends Controller
{
    private const int PER_PAGE = 20;

    private const array SUMMARY_COLUMNS = ['id', 'created_by', 'title', 'date', 'end_date', 'description', 'location', 'region_id', 'prov_id', 'city_id', 'brgy_id', 'agency_id', 'thumbnail_path', 'created_at'];

    public function __construct(private ProjectThumbnailStore $thumbnails) {}

    public function index(IndexProjectsRequest $request): Response
    {
        $search = $request->validated('q');

        $projects = Project::query()
            ->select(self::SUMMARY_COLUMNS)
            ->withPlace()
            ->with('agency:id,name,acronym')
            ->when($search, fn (Builder $query, string $q) => $query->whereLike('title', '%'.addcslashes($q, '%_\\').'%'))
            ->withCount(['series', 'records'])
            ->orderByDesc('date')
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Project $project): array => [
                ...$this->summary($project),
                'series_count' => $project->series_count ?? 0,
                'records_count' => $project->records_count ?? 0,
            ]);

        return Inertia::render('encoder/projects/index-page/index', [
            'projects' => $projects,
            'filters' => ['q' => $search ?? ''],
        ]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $project = DB::transaction(function () use ($request, $user): Project {
            $project = new Project($request->safe()->except('thumbnail'));
            $project->created_by = $user->id;
            $project->save();

            if ($request->hasFile('thumbnail')) {
                $this->thumbnails->replace($project, $request->file('thumbnail'));
            }

            return $project;
        });

        return response()->json([...$this->summary($this->withNames($project)), 'series' => []], 201);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project->fill($request->safe()->except(['thumbnail', 'remove_thumbnail']));
        $project->save();

        if ($request->hasFile('thumbnail')) {
            $this->thumbnails->replace($project, $request->file('thumbnail'));
        } elseif ($request->boolean('remove_thumbnail')) {
            $this->thumbnails->remove($project);
        }

        return response()->json($this->summary($this->withNames($project->refresh())));
    }

    /**
     * Soft delete: the project leaves the lists and the picker, its videos stay
     * in the archive (still labelled with it) and its thumbnail is kept so it
     * can be restored.
     */
    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * The cover image, from the private media disk, behind the policy.
     */
    public function thumbnail(Project $project): BinaryFileResponse
    {
        Gate::authorize('view', $project);

        $disk = Storage::disk('media');
        abort_unless($project->thumbnail_path !== null && $disk->exists($project->thumbnail_path), 404);

        return response()->file($disk->path($project->thumbnail_path), [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A project, its series, and the records filed under it (an encoder sees
     * only their own records, as everywhere else).
     */
    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        /** @var User $user */
        $user = $request->user();

        $records = $project->records()
            ->select(['id', 'title', 'project_series_id', 'processing_status', 'review_status', 'created_at'])
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->where('created_by', $user->id))
            ->with('projectSeries:id,title')
            ->latest()
            ->paginate(self::PER_PAGE)
            ->through(fn (Record $record): array => [
                'id' => $record->id,
                'title' => $record->title,
                'series' => $record->projectSeries?->title,
                'processing_status' => $record->processing_status->value,
                'review_status' => $record->review_status->value,
                'created_at' => $record->created_at?->toIso8601String(),
            ]);

        return Inertia::render('encoder/projects/show-page/show', [
            'project' => $this->summary($this->withNames($project)),
            'series' => $project->series()
                ->select(['id', 'project_id', 'title', 'date', 'end_date', 'description', 'location', 'region_id', 'prov_id', 'city_id', 'brgy_id', 'agency_id'])
                ->withPlace()
                ->withCount('records')
                ->orderBy('date')
                ->orderBy('title')
                ->get()
                ->map(fn (ProjectSeries $series): array => [
                    'id' => $series->id,
                    'title' => $series->title,
                    'date' => $series->date?->toDateString(),
                    'end_date' => $series->end_date?->toDateString(),
                    'description' => $series->description,
                    'location' => $series->location,
                    'place' => $series->placeLabel(),
                    'region_id' => $series->region_id,
                    'prov_id' => $series->prov_id,
                    'city_id' => $series->city_id,
                    'brgy_id' => $series->brgy_id,
                    'agency_id' => $series->agency_id,
                    'records_count' => $series->records_count ?? 0,
                ])
                ->all(),
            'records' => $records,
            'canEdit' => $user->can('update', $project),
            'canDelete' => $user->can('delete', $project),
        ]);
    }

    private function withNames(Project $project): Project
    {
        return $project->loadMissing(['barangay:id,name', 'city:id,name', 'province:id,name', 'region:id,name', 'agency:id,name,acronym']);
    }

    /**
     * Everything the cards, the picker and the edit form need.
     *
     * @return array<string, mixed>
     */
    private function summary(Project $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'date' => $project->date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'description' => $project->description,
            'location' => $project->location,
            'place' => $project->placeLabel(),
            'region_id' => $project->region_id,
            'prov_id' => $project->prov_id,
            'city_id' => $project->city_id,
            'brgy_id' => $project->brgy_id,
            'agency' => $project->agency?->summary(),
            'thumbnail_url' => $project->thumbnail_path !== null
                ? route('projects.thumbnail', ['project' => $project, 'v' => substr(md5($project->thumbnail_path), 0, 8)])
                : null,
        ];
    }
}
