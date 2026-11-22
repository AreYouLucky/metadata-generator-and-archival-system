<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\DestroyRecordFrameRequest;
use App\Http\Requests\Encoder\StoreRecordFrameRequest;
use App\Http\Requests\Encoder\UpdateRecordFrameRequest;
use App\Models\Record;
use App\Models\RecordFrame;
use App\Services\Records\AuditTrail;
use App\Services\Records\FrameCapture;
use App\Services\Records\VisualsPresenter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Encoders' changes to the key frames: add one at a time in the video,
 * correct a frame's description and on-screen text, or remove one. The
 * stored description and text feed metadata generation. Re-running
 * processing replaces every frame, added ones included.
 */
class RecordFrameController extends Controller
{
    private const array LABELS = ['description' => 'description', 'ocr_text' => 'text on screen'];

    /**
     * Capture the master at the time (about a second on the AI service) as a
     * new key frame. It isn't analysed: the encoder writes what it shows.
     */
    public function store(StoreRecordFrameRequest $request, Record $record, FrameCapture $capture, AuditTrail $audit): JsonResponse
    {
        $seconds = $request->seconds();
        $path = "records/{$record->id}/frames/added_".Str::lower(Str::random(20)).'.jpg';

        try {
            $capture->capture($record, $seconds, $path);
        } catch (ConnectionException) {
            abort(503, 'The AI service could not be reached to capture the frame.');
        } catch (RequestException $exception) {
            $detail = $exception->response->json('detail');
            Log::warning('Key frame capture failed', ['record_id' => $record->id, 'status' => $exception->response->status()]);
            abort(422, is_string($detail) ? $detail : 'The frame could not be captured.');
        }

        $frame = $record->frames()->create([
            'time_sec' => $seconds,
            'path' => $path,
            'is_scene_start' => false,
            'description' => $request->description(),
        ]);

        $audit->log($record, AuditAction::Updated, $request->user(), 'Key frame added at '.VisualsPresenter::clock($seconds).'.');

        return response()->json(VisualsPresenter::frame($record, $frame), 201);
    }

    public function update(UpdateRecordFrameRequest $request, Record $record, RecordFrame $frame, AuditTrail $audit): JsonResponse
    {
        $frame->fill($request->changes());

        if ($frame->isDirty()) {
            $changed = array_map(fn (string $column): string => self::LABELS[$column], array_keys($frame->getDirty()));
            $frame->save();

            $audit->log($record, AuditAction::Updated, $request->user(), sprintf(
                'Key frame at %s edited: %s.',
                VisualsPresenter::clock($frame->time_sec),
                implode(', ', $changed),
            ));
        }

        return response()->json(VisualsPresenter::frame($record, $frame));
    }

    /**
     * Remove the frame and its image. A thumbnail made from it is a separate
     * copy, so it stays (only its link to the frame is cleared).
     */
    public function destroy(DestroyRecordFrameRequest $request, Record $record, RecordFrame $frame, AuditTrail $audit): Response
    {
        $frame->delete();

        // Only images in this record's frames folder, never anything else on the disk.
        if (str_starts_with($frame->path, "records/{$record->id}/frames/")) {
            Storage::disk('media')->delete($frame->path);
        }

        $audit->log($record, AuditAction::Updated, $request->user(), 'Key frame removed at '.VisualsPresenter::clock($frame->time_sec).'.');

        return response()->noContent();
    }
}
