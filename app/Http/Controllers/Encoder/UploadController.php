<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\StartUploadRequest;
use App\Http\Requests\Encoder\UploadChunkRequest;
use App\Models\Record;
use App\Models\User;
use App\Services\Uploads\ChunkedUploadStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Chunked, resumable uploads (PHP's post_max_size is far below video sizes).
 * The browser starts an upload, sends 8 MB chunks in order, then creates the
 * record with RecordController::store.
 */
class UploadController extends Controller
{
    public function __construct(private ChunkedUploadStore $uploads) {}

    public function store(StartUploadRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $upload = $this->uploads->start($user, $request->safeFilename(), $request->extension(), (int) $request->validated('size'));

        return response()->json($upload, 201);
    }

    /**
     * Where to resume: how many bytes the server already has.
     */
    public function show(Request $request, string $upload): JsonResponse
    {
        Gate::authorize('create', Record::class);

        /** @var User $user */
        $user = $request->user();
        $session = $this->uploads->status($upload, $user);

        return response()->json(['id' => $upload, 'received' => $session['received'], 'size' => $session['size']]);
    }

    public function chunk(UploadChunkRequest $request, string $upload): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $received = $this->uploads->append($upload, $user, (int) $request->validated('offset'), $request->file('chunk'));

        return response()->json(['id' => $upload, 'received' => $received]);
    }
}
