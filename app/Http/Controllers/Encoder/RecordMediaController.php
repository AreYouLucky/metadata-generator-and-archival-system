<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\FileRole;
use App\Http\Controllers\Controller;
use App\Models\Record;
use App\Models\RecordFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecordMediaController extends Controller
{
    /**
     * Streams the record's playable file to the workspace player after a
     * policy check: the master when browsers can play it, otherwise its access
     * copy (proxy). Media lives on the private disk and is never served from a
     * public URL; BinaryFileResponse answers Range requests, so seeking works.
     */
    public function __invoke(Record $record): BinaryFileResponse
    {
        Gate::authorize('view', $record);

        $file = self::playableFile($record);
        abort_if($file === null, 404);

        $disk = Storage::disk('media');
        abort_unless($disk->exists($file->storage_location), 404);

        return response()->file($disk->path($file->storage_location), [
            'Content-Type' => $file->mime_type ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The master, unless it is known not to play in browsers and an access
     * copy exists. Before probing, the master is tried.
     */
    public static function playableFile(Record $record): ?RecordFile
    {
        $files = $record->files()
            ->whereIn('role', [FileRole::Master, FileRole::Proxy])
            ->with('technicalMetadata:id,record_file_id,video_codec,audio_codec')
            ->get(['id', 'record_id', 'role', 'storage_location', 'mime_type']);

        $master = $files->first(fn (RecordFile $file): bool => $file->role === FileRole::Master);
        $proxy = $files->first(fn (RecordFile $file): bool => $file->role === FileRole::Proxy);
        $masterPlays = $master?->technicalMetadata?->isBrowserPlayable($master->mime_type) ?? true;

        return $masterPlays || $proxy === null ? $master : $proxy;
    }
}
