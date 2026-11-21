<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\FileRole;
use App\Http\Controllers\Controller;
use App\Models\Record;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecordMediaController extends Controller
{
    /**
     * Streams the master to the workspace player after a policy check. Media
     * lives on the private disk and is never served from a public URL;
     * BinaryFileResponse answers Range requests, so seeking works.
     */
    public function __invoke(Record $record): BinaryFileResponse
    {
        Gate::authorize('view', $record);

        $file = $record->files()->where('role', FileRole::Master)->firstOrFail(['id', 'record_id', 'storage_location', 'mime_type']);
        $disk = Storage::disk('media');

        abort_unless($disk->exists($file->storage_location), 404);

        return response()->file($disk->path($file->storage_location), [
            'Content-Type' => $file->mime_type ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
