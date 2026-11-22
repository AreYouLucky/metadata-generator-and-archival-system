<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\SetThumbnailRequest;
use App\Models\Record;
use App\Models\RecordFrame;
use App\Models\User;
use App\Services\Records\RecordThumbnails;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ThumbnailController extends Controller
{
    /**
     * Choose the thumbnail: a key frame, the frame at a time (captured from
     * the master by the AI service, about a second), or an uploaded image.
     */
    public function store(SetThumbnailRequest $request, Record $record, RecordThumbnails $thumbnails): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (isset($data['frame_id'])) {
            /** @var RecordFrame $frame */
            $frame = $record->frames()->findOrFail((int) $data['frame_id']);
            $thumbnails->useFrame($record, $frame, $user);
        } elseif (isset($data['time_sec'])) {
            try {
                $thumbnails->capture($record, (float) $data['time_sec'], $user);
            } catch (ConnectionException) {
                abort(503, 'The AI service could not be reached to capture the frame.');
            } catch (RequestException $exception) {
                $detail = $exception->response->json('detail');
                Log::warning('Thumbnail capture failed', ['record_id' => $record->id, 'status' => $exception->response->status()]);
                abort(422, is_string($detail) ? $detail : 'The frame could not be captured.');
            }
        } else {
            /** @var UploadedFile $image */
            $image = $request->file('image');
            $thumbnails->upload($record, $image, $user);
        }

        return response()->json(['thumbnail' => $thumbnails->present($record)]);
    }

    /**
     * The thumbnail image, behind the record policy.
     */
    public function show(Record $record): BinaryFileResponse
    {
        Gate::authorize('view', $record);

        $disk = Storage::disk('media');
        abort_unless($record->thumbnail_path !== null && $disk->exists($record->thumbnail_path), 404);

        return response()->file($disk->path($record->thumbnail_path), [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
