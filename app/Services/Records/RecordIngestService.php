<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Enums\FileRole;
use App\Enums\PipelineStage;
use App\Jobs\StartImportJob;
use App\Jobs\StartProcessingJob;
use App\Models\Batch;
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
 * Turns finished uploads and web addresses into records. An upload is checked
 * (what the file really is), moved to its permanent place and processed at
 * once; a URL import gets its record now and its master file once the AI
 * service has downloaded it (ImportResultController). Several at once form a
 * batch. Every record gets its pending stages, and its processing options are
 * kept on it.
 *
 * @phpstan-type Filing array{title?: string|null, project_id?: string|null, project_series_id?: string|null, agency_id?: int|null, division_id?: int|null, expected_md5?: string|null, expected_sha256?: string|null}
 */
class RecordIngestService
{
    /**
     * Detected MIME types accepted besides video/*. MXF is reported as application/mxf.
     */
    public const array EXTRA_MIME_TYPES = ['application/mxf'];

    public function __construct(
        private ChunkedUploadStore $uploads,
        private AccessionNumbers $accessionNumbers,
    ) {}

    /**
     * One uploaded file becomes a record; processing is queued.
     *
     * @param  Filing  $details
     */
    public function ingest(User $user, string $uploadId, array $details, PipelineOptions $options, ?Batch $batch = null): Record
    {
        $upload = $this->uploads->inspect($uploadId, $user);
        $mimeType = $this->videoMimeType($upload['path'], 'upload_id');
        ['session' => $session, 'path' => $partPath] = $this->uploads->claim($uploadId, $user);

        $record = DB::transaction(function () use ($user, $session, $partPath, $details, $mimeType, $options, $batch): Record {
            $record = $this->createRecord($user, $this->title($details['title'] ?? null, $session['original_filename']), $details, $options, $batch, null);

            $fileId = (string) Str::ulid();
            $location = "records/{$record->id}/master/{$fileId}.{$session['extension']}";
            Storage::disk('media')->move($partPath, $location);

            $file = $this->attachMaster($record, $fileId, $location, $session['original_filename'], $mimeType, $session['size'], $details);

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
     * A web address becomes a record without a file; the AI service downloads
     * it (yt-dlp) and calls back, then processing starts with these options.
     *
     * @param  Filing  $details
     */
    public function import(User $user, string $url, array $details, PipelineOptions $options, ?Batch $batch = null): Record
    {
        $record = DB::transaction(fn (): Record => $this->createRecord(
            $user,
            Str::limit(trim((string) ($details['title'] ?? '')), 250, '') ?: self::importPlaceholderTitle($url),
            $details,
            $options,
            $batch,
            $url,
        ));

        StartImportJob::dispatch($record)->afterCommit();

        return $record;
    }

    /**
     * Several uploads and web addresses at once, filed and processed alike.
     * Every upload is checked before anything is created, so one wrong file
     * doesn't leave half a batch behind.
     *
     * @param  list<array{upload_id: string|null, url: string|null, title: string|null}>  $items
     * @param  Filing  $filing
     */
    public function ingestBatch(User $user, ?string $name, array $items, array $filing, PipelineOptions $options): Batch
    {
        foreach ($items as $index => $item) {
            if ($item['upload_id'] !== null) {
                $this->videoMimeType($this->uploads->inspect($item['upload_id'], $user)['path'], "items.{$index}.upload_id");
            }
        }

        return DB::transaction(function () use ($user, $name, $items, $filing, $options): Batch {
            $batch = new Batch(['name' => trim((string) $name) ?: null]);
            $batch->created_by = $user->id;
            $batch->total = count($items);
            $batch->save();

            foreach ($items as $item) {
                $details = [...$filing, 'title' => $item['title']];

                $item['upload_id'] !== null
                    ? $this->ingest($user, $item['upload_id'], $details, $options, $batch)
                    : $this->import($user, (string) $item['url'], $details, $options, $batch);
            }

            return $batch;
        });
    }

    /**
     * The downloaded file of a URL import becomes the record's master.
     */
    public function attachImportedMaster(Record $record, string $fileId, string $location, string $originalFilename, string $mimeType, int $size): RecordFile
    {
        return $this->attachMaster($record, $fileId, $location, $originalFilename, $mimeType, $size, []);
    }

    /**
     * Until the download reports the video's own title.
     */
    public static function importPlaceholderTitle(string $url): string
    {
        $host = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));

        return 'Import from '.($host !== '' && $host !== null ? $host : 'the web');
    }

    /**
     * Whether a stored file's detected type is a video; throws a validation
     * error on `$attribute` (and deletes the part) when it isn't.
     */
    public function videoMimeType(string $path, string $attribute): string
    {
        $disk = Storage::disk('media');
        $mimeType = (string) $disk->mimeType($path);

        if (! self::isVideoMimeType($mimeType)) {
            $disk->delete($path);

            throw ValidationException::withMessages([
                $attribute => 'The file is not a video (detected type: '.($mimeType ?: 'unknown').').',
            ]);
        }

        return $mimeType;
    }

    public static function isVideoMimeType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'video/') || in_array($mimeType, self::EXTRA_MIME_TYPES, true);
    }

    /**
     * @param  Filing  $details
     */
    private function createRecord(User $user, string $title, array $details, PipelineOptions $options, ?Batch $batch, ?string $sourceUrl): Record
    {
        $record = new Record([
            'title' => $title,
            'project_id' => $details['project_id'] ?? null,
            'project_series_id' => $details['project_series_id'] ?? null,
            'agency_id' => $details['agency_id'] ?? null,
            'division_id' => $details['division_id'] ?? null,
        ]);
        $record->created_by = $user->id;
        $record->batch_id = $batch?->id;
        $record->source_url = $sourceUrl;
        $record->pipeline_options = $options->toArray();
        // Assigned at ingest (PROJECT_SPEC §11); editable later, always unique.
        $record->accession_number = $this->accessionNumbers->next();
        $record->save();

        $stages = $sourceUrl === null ? PipelineStage::active() : PipelineStage::tracked();
        $record->stages()->createMany(array_map(fn (PipelineStage $stage): array => ['stage' => $stage], $stages));

        return $record;
    }

    /**
     * @param  Filing  $details
     */
    private function attachMaster(Record $record, string $fileId, string $location, string $originalFilename, string $mimeType, int $size, array $details): RecordFile
    {
        $file = new RecordFile([
            'role' => FileRole::Master,
            'storage_location' => $location,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'file_size' => $size,
            'expected_md5' => isset($details['expected_md5']) ? strtolower($details['expected_md5']) : null,
            'expected_sha256' => isset($details['expected_sha256']) ? strtolower($details['expected_sha256']) : null,
        ]);
        $file->id = $fileId;
        $record->files()->save($file);

        return $file;
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
