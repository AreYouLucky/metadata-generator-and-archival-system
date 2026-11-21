<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Enums\FileRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\StoreRecordFileRequest;
use App\Jobs\IngestFileJob;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\User;
use App\Services\Records\AuditTrail;
use App\Services\Uploads\ChunkedUploadStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordFileController extends Controller
{
    /**
     * Content types accepted for extra files: video, plus MXF (reported as application/mxf).
     */
    private const array EXTRA_MIME_TYPES = ['application/mxf'];

    /**
     * Turn a finished chunked upload into an extra file of the record, then
     * checksum and probe it in the background (POST /jobs/ingest-file).
     */
    public function store(StoreRecordFileRequest $request, Record $record, ChunkedUploadStore $uploads, AuditTrail $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        ['session' => $session, 'path' => $partPath] = $uploads->claim((string) $request->validated('upload_id'), $user);
        $disk = Storage::disk('media');
        $mimeType = (string) $disk->mimeType($partPath);

        if (! str_starts_with($mimeType, 'video/') && ! in_array($mimeType, self::EXTRA_MIME_TYPES, true)) {
            $disk->delete($partPath);

            throw ValidationException::withMessages(['upload_id' => 'The file is not a video (detected type: '.($mimeType ?: 'unknown').').']);
        }

        $role = FileRole::from((string) $request->validated('role'));
        $file = DB::transaction(function () use ($record, $session, $partPath, $mimeType, $role, $request, $disk, $user, $audit): RecordFile {
            $id = (string) Str::ulid();
            $location = "records/{$record->id}/{$role->value}/{$id}.{$session['extension']}";
            $disk->move($partPath, $location);

            $file = new RecordFile([
                'role' => $role,
                'storage_location' => $location,
                'original_filename' => $session['original_filename'],
                'mime_type' => $mimeType,
                'file_size' => $session['size'],
                'expected_md5' => is_string($request->validated('expected_md5')) ? strtolower($request->validated('expected_md5')) : null,
                'expected_sha256' => is_string($request->validated('expected_sha256')) ? strtolower($request->validated('expected_sha256')) : null,
            ]);
            $file->id = $id;
            $record->files()->save($file);

            $audit->log($record, AuditAction::Uploaded, $user, "{$role->label()} file added: {$file->original_filename}", $file->id);

            return $file;
        });

        IngestFileJob::dispatch($file);

        return response()->json(['id' => $file->id], 201);
    }
}
