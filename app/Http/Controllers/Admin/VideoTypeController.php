<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVideoTypeRequest;
use App\Http\Requests\Admin\UpdateVideoTypeRequest;
use App\Models\VideoType;
use App\Services\Lookups\LookupList;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for video types (PROJECT_SPEC §8). Encoders pick from the list in
 * the Archive panel; the public catalogue filters by it.
 */
class VideoTypeController extends Controller
{
    public function index(LookupList $lookups): Response
    {
        Gate::authorize('viewAny', VideoType::class);

        return Inertia::render('admin/video-types-page/video-types', [
            'items' => $lookups->all(VideoType::class),
        ]);
    }

    public function store(StoreVideoTypeRequest $request, LookupList $lookups): JsonResponse
    {
        /** @var array{name: string, sort_order?: int|null} $data */
        $data = $request->validated();

        return response()->json(LookupList::present($lookups->create(VideoType::class, $data)), 201);
    }

    public function update(UpdateVideoTypeRequest $request, VideoType $videoType, LookupList $lookups): JsonResponse
    {
        /** @var array{name: string, sort_order?: int|null} $data */
        $data = $request->validated();

        return response()->json(LookupList::present($lookups->update($videoType, $data)));
    }

    public function destroy(VideoType $videoType, LookupList $lookups): JsonResponse
    {
        Gate::authorize('delete', $videoType);

        abort_if($lookups->isInUse($videoType), 422, 'Records still use this video type, so it cannot be deleted. Rename it instead.');

        $videoType->delete();

        return response()->json(['deleted' => true]);
    }
}
