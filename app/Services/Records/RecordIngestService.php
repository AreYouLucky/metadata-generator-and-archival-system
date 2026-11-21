<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Enums\FileRole;
use App\Enums\PipelineStage;
use App\Jobs\StartProcessingJob;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\User;
use App\Models\VideoAuditLog;
use App\Services\Uploads\ChunkedUploadStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a finished upload into a record: checks what the file really is, moves
 * it to its permanent place, creates the record, its master file, the pending
 * stages and the `uploaded` audit entry, then queues processing.
 */
class RecordIngestService
{
    /**
     * Detected MIME types accepted besides video/*. MXF is reported as application/mxf.
     */
    private const array EXTRA_MIME_TYPES = ['application/mxf'];

    public function __construct(private ChunkedUploadStore $uploads) {}

    /**
     * @param  array{title?: string|null, project_id?: string|null, project_series_id?: string|null, agency_id?: int|null, division_id?: int|null, expected_md5?: string|null, expected_sha256?: string|null}  $details
     */
    public function ingest(User $user, string $uploadId, array $details, PipelineOptions $options): Record
    {
        ['session' => $session, 'path' => $partPath] = $this->uploads->claim($uploadId, $user);
        $disk = Storage::disk('media');

        $mimeType = (string) $disk->mimeType($partPath);

        if (! str_starts_with($mimeType, 'video/') && ! in_array($mimeType, self::EXTRA_MIME_TYPES, true)) {
            $disk->delete($partPath);

            throw ValidationException::withMessages([
                'upload_id' => 'The file is not a video (detected type: '.($mimeType ?: 'unknown').').',
            ]);
        }

        $record = DB::transaction(function () use ($user, $session, $partPath, $details, $mimeType, $disk): Record {
            $record = new Record([
                'title' => $this->title($details['title'] ?? null, $session['original_filename']),
                'project_id' => $details['project_id'] ?? null,
                'project_series_id' => $details['project_series_id'] ?? null,
                'agency_id' => $details['agency_id'] ?? null,
                'division_id' => $details['division_id'] ?? null,
            ]);
            $record->created_by = $user->id;
            $record->save();

            $fileId = (string) Str::ulid();
            $location = "records/{$record->id}/master/{$fileId}.{$session['extension']}";
            $disk->move($partPath, $location);

            $file = new RecordFile([
                'role' => FileRole::Master,
                'storage_location' => $location,
                'original_filename' => $session['original_filename'],
                'mime_type' => $mimeType,
                'file_size' => $session['size'],
                'expected_md5' => isset($details['expected_md5']) ? strtolower($details['expected_md5']) : null,
                'expected_sha256' => isset($details['expected_sha256']) ? strtolower($details['expected_sha256']) : null,
            ]);
            $file->id = $fileId;
            $record->files()->save($file);

            $record->stages()->createMany(array_map(
                fn (PipelineStage $stage): array => ['stage' => $stage],
                PipelineStage::active(),
            ));

            VideoAuditLog::create([
                'record_id' => $record->id,
                'record_file_id' => $file->id,
                'action' => AuditAction::Uploaded,
                'performed_by' => $user->id,
                'performed_at' => now(),
                'remarks' => null,
            ]);

            return $record;
        });

        StartProcessingJob::dispatch($record, $options)->afterCommit();

        return $record;
    }

    /**
     * The encoder's title, or the file name without its extension.
     */
    private function title(?string $title, string $originalFilename): string
    {
        $title = trim((string) $title);

        if ($title !== '') {
            return Str::limit($title, 250, '');
        }

        $fromFile = trim(str_replace(['_', '-'], ' ', pathinfo($originalFilename, PATHINFO_FILENAME)));

        return Str::limit($fromFile !== '' ? $fromFile : 'Untitled video', 250, '');
    }
}
