<?php

namespace App\Http\Controllers\User;

use App\Enums\FileRole;
use App\Exporters\ExportOptions;
use App\Exporters\VttExporter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Encoder\RecordMediaController;
use App\Http\Requests\User\ShowCatalogueCaptionsRequest;
use App\Models\Project;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordFrame;
use App\Services\Catalogue\Catalogue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Files behind the public catalogue, each checked against the distribution
 * rule first. A record that may not leave the archive answers 404, never 403,
 * so its existence isn't revealed.
 */
class CatalogueMediaController extends Controller
{
    /**
     * The access copy for the public player: an uploaded access file that
     * browsers can play, else the proxy when the master can't play, else the
     * master itself. Served inline with Range support; never as a download.
     */
    public function stream(Record $record): BinaryFileResponse
    {
        abort_unless(Gate::forUser(null)->allows('distribute', $record), 404);

        $file = self::accessCopy($record);
        $disk = Storage::disk('media');
        abort_unless($file !== null && $disk->exists($file->storage_location), 404);

        return response()->file($disk->path($file->storage_location), [
            'Content-Type' => $file->mime_type ?? 'video/mp4',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function captions(ShowCatalogueCaptionsRequest $request, Record $record, VttExporter $exporter): Response
    {
        return response($exporter->export($record, new ExportOptions(language: (string) $request->validated('language'))), 200, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A public record's thumbnail (frame, captured or uploaded image).
     */
    public function thumbnail(Record $record): BinaryFileResponse
    {
        abort_unless(Gate::forUser(null)->allows('distribute', $record), 404);

        $disk = Storage::disk('media');
        abort_unless($record->thumbnail_path !== null && $disk->exists($record->thumbnail_path), 404);

        return response()->file($disk->path($record->thumbnail_path), [
            // Private: the record can leave the catalogue (reopened, embargoed).
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A key frame's image on a public record's page. Only frames of that
     * record are bound (scoped route).
     */
    public function frame(Record $record, RecordFrame $frame): BinaryFileResponse
    {
        abort_unless(Gate::forUser(null)->allows('distribute', $record), 404);

        $disk = Storage::disk('media');
        abort_unless($disk->exists($frame->path), 404);

        return response()->file($disk->path($frame->path), [
            'Content-Type' => 'image/jpeg',
            // Private: the record can leave the catalogue (reopened, embargoed).
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A project's cover, only while the project has public records (so
     * internal projects stay invisible).
     */
    public function projectThumbnail(Project $project, Catalogue $catalogue): BinaryFileResponse
    {
        abort_unless($catalogue->records()->where('records.project_id', $project->id)->exists(), 404);

        $disk = Storage::disk('media');
        abort_unless($project->thumbnail_path !== null && $disk->exists($project->thumbnail_path), 404);

        return response()->file($disk->path($project->thumbnail_path), [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function accessCopy(Record $record): ?RecordFile
    {
        $access = $record->files()
            ->where('role', FileRole::Access)
            ->with('technicalMetadata:id,record_file_id,video_codec,audio_codec')
            ->latest()
            ->first(['id', 'record_id', 'role', 'storage_location', 'mime_type', 'created_at']);

        if ($access !== null && ($access->technicalMetadata?->isBrowserPlayable($access->mime_type) ?? false)) {
            return $access;
        }

        return RecordMediaController::playableFile($record);
    }
}
