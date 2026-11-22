<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGenreRequest;
use App\Http\Requests\Admin\UpdateGenreRequest;
use App\Models\Genre;
use App\Services\Lookups\LookupList;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for genres (PROJECT_SPEC §8). Encoders pick from the list in the
 * Archive panel; the public catalogue filters by it.
 */
class GenreController extends Controller
{
    public function index(LookupList $lookups): Response
    {
        Gate::authorize('viewAny', Genre::class);

        return Inertia::render('admin/genres-page/genres', [
            'items' => $lookups->all(Genre::class),
        ]);
    }

    public function store(StoreGenreRequest $request, LookupList $lookups): JsonResponse
    {
        /** @var array{name: string, sort_order?: int|null} $data */
        $data = $request->validated();

        return response()->json(LookupList::present($lookups->create(Genre::class, $data)), 201);
    }

    public function update(UpdateGenreRequest $request, Genre $genre, LookupList $lookups): JsonResponse
    {
        /** @var array{name: string, sort_order?: int|null} $data */
        $data = $request->validated();

        return response()->json(LookupList::present($lookups->update($genre, $data)));
    }

    public function destroy(Genre $genre, LookupList $lookups): JsonResponse
    {
        Gate::authorize('delete', $genre);

        abort_if($lookups->isInUse($genre), 422, 'Records still use this genre, so it cannot be deleted. Rename it instead.');

        $genre->delete();

        return response()->json(['deleted' => true]);
    }
}
