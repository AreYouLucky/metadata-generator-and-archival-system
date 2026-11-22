<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\DestroyClipRequest;
use App\Http\Requests\Encoder\ShowClipRequest;
use App\Http\Requests\Encoder\StoreClipRequest;
use App\Models\Clip;
use App\Models\Record;
use App\Services\Records\AuditTrail;
use App\Services\Records\ClipCutter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Clips (Phase 7, the workspace's Clips tab): cut a part of the master to
 * its own MP4 in the background, download it, delete it. The list itself is
 * the workspace's `clips` prop.
 */
class ClipController extends Controller
{
    public function store(StoreClipRequest $request, Record $record, ClipCutter $cutter): JsonResponse
    {
        $clip = $cutter->create(
            $record,
            $request->user(),
            (float) $request->validated('start'),
            (float) $request->validated('end'),
            $request->validated('title'),
        );

        return response()->json(['clip' => $clip->refresh()->present()], 202);
    }

    /**
     * Download a finished clip. Each download is logged as an export.
     */
    public function show(ShowClipRequest $request, Record $record, Clip $clip, AuditTrail $audit): BinaryFileResponse
    {
        $disk = Storage::disk('media');
        abort_unless($clip->status === TaskStatus::Done && $disk->exists($clip->storagePath()), 404);

        $audit->log($record, AuditAction::Exported, $request->user(), "Clip: {$clip->label()}");

        $name = Str::slug($clip->title ?? '') ?: (Str::slug($record->title) ?: 'record').'-clip-'.$clip->id;

        return response()->download($disk->path($clip->storagePath()), "{$name}.mp4", [
            'Content-Type' => 'video/mp4',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Delete a clip and its file (not while it is being cut).
     */
    public function destroy(DestroyClipRequest $request, Record $record, Clip $clip, ClipCutter $cutter): Response
    {
        abort_if($clip->isBusy(), 409, 'Wait until the clip has been cut.');

        $cutter->delete($clip);

        return response()->noContent();
    }
}
