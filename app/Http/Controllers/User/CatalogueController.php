<?php

namespace App\Http\Controllers\User;

use App\Enums\AccessAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\BrowseCatalogueRequest;
use App\Models\AccessLog;
use App\Models\Project;
use App\Models\ProjectSeries;
use App\Models\Record;
use App\Services\Catalogue\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public catalogue pages (PROJECT_SPEC §8, public catalogue): home,
 * browse, project and series pages, and the watch page. Open to guests
 * unless PUBLIC_CATALOGUE_REQUIRES_LOGIN; read-only, rate-limited, cached.
 */
class CatalogueController extends Controller
{
    public function __construct(private Catalogue $catalogue) {}

    public function home(): Response
    {
        return Inertia::render('user/home-page/home', $this->catalogue->home());
    }

    public function browse(BrowseCatalogueRequest $request): Response
    {
        $filters = $request->filters();
        $facets = $this->catalogue->facets();

        return Inertia::render('user/browse-page/browse', [
            'records' => $this->catalogue->browse($filters, $request->page()),
            'filters' => $filters,
            'facets' => $facets,
            'months' => $filters['year'] === null ? [] : $this->catalogue->months($filters['year']),
            'seriesOptions' => $filters['project'] === null ? [] : $this->catalogue->seriesOptions($filters['project']),
        ]);
    }

    public function project(BrowseCatalogueRequest $request, Project $project): Response
    {
        $page = $this->catalogue->project($project, $request->validated('series'), $request->page());
        abort_if($page === null, 404);

        return Inertia::render('user/project-page/project', [
            ...$page,
            'activeSeries' => $request->validated('series'),
        ]);
    }

    public function series(BrowseCatalogueRequest $request, ProjectSeries $series): Response
    {
        $page = $this->catalogue->series($series, $request->page());
        abort_if($page === null, 404);

        return Inertia::render('user/series-page/series', $page);
    }

    /**
     * Each view is written to the access log (PROJECT_SPEC §8). The IP
     * address is kept for audit and shown to admins only.
     */
    public function watch(Request $request, Record $record): Response
    {
        abort_unless(Gate::forUser(null)->allows('distribute', $record), 404);

        AccessLog::create([
            'record_id' => $record->id,
            'user_id' => $request->user()?->id,
            'action' => AccessAction::View,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'accessed_at' => now(),
        ]);

        return Inertia::render('user/watch-page/watch', [
            ...$this->catalogue->watch($record),
            'mediaUrl' => route('catalogue.media', $record),
        ]);
    }
}
