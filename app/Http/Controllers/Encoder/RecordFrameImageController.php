<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Models\Record;
use App\Models\RecordFrame;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecordFrameImageController extends Controller
{
    /**
     * A key frame's image, behind the record policy. Frames get new ids when
     * the pipeline runs again, so the URL can be cached.
     */
    public function __invoke(Record $record, RecordFrame $frame): BinaryFileResponse
    {
        Gate::authorize('view', $record);

        $disk = Storage::disk('media');
        abort_unless($disk->exists($frame->path), 404);

        return response()->file($disk->path($frame->path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
