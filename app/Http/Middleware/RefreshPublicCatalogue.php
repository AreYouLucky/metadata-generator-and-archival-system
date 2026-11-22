<?php

namespace App\Http\Middleware;

use App\Enums\ReviewStatus;
use App\Models\Genre;
use App\Models\ProductionAgency;
use App\Models\Project;
use App\Models\ProjectSeries;
use App\Models\Record;
use App\Models\VideoType;
use App\Services\Catalogue\CatalogueCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clears the public catalogue cache after a successful change that the public
 * site shows: any edit to an approved record (only admins can make one: its
 * transcript, credits, subjects, rights…), and edits to projects, series,
 * agencies, genres and video types. Edits to drafts leave the cache alone.
 */
class RefreshPublicCatalogue
{
    private const array SHOWN_PUBLICLY = [Project::class, ProjectSeries::class, ProductionAgency::class, Genre::class, VideoType::class];

    public function __construct(private CatalogueCache $cache) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodSafe() && $response->isSuccessful() && $this->touchesPublicContent($request)) {
            $this->cache->flush();
        }

        return $response;
    }

    private function touchesPublicContent(Request $request): bool
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Record && $parameter->review_status === ReviewStatus::Approved) {
                return true;
            }

            if (is_object($parameter) && in_array($parameter::class, self::SHOWN_PUBLICLY, true)) {
                return true;
            }
        }

        return false;
    }
}
