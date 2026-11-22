<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Enums\FileRole;
use App\Enums\PipelineStage;
use App\Enums\SourceCarrier;
use App\Jobs\StartProcessingJob;
use App\Models\Record;
use App\Models\VideoAuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finishes a URL import: the downloaded file becomes the record's master
 * (after the same video check as an upload), the site's title replaces the
 * placeholder, where it came from goes into the provenance notes, and
 * processing starts with the options chosen on import.
 */
class ImportRecorder
{
    public function __construct(
        private RecordIngestService $ingest,
        private PipelineRecorder $recorder,
    ) {}

    /**
     * @param  array{title: string|null, uploader: string|null, upload_date: string|null, extractor: string|null, webpage_url: string|null}  $source
     */
    public function store(Record $record, string $path, int $fileSize, array $source): void
    {
        $disk = Storage::disk('media');

        if (! $disk->exists($path)) {
            $this->recorder->failImport($record, 'The downloaded file could not be found.');

            return;
        }

        $mimeType = (string) $disk->mimeType($path);

        if (! RecordIngestService::isVideoMimeType($mimeType)) {
            $disk->delete($path);
            $this->recorder->failImport($record, 'The downloaded file is not a video (detected type: '.($mimeType ?: 'unknown').').');

            return;
        }

        // A retried callback, or a second download, must not add a second master.
        if ($record->files()->where('role', FileRole::Master)->exists()) {
            return;
        }

        DB::transaction(function () use ($record, $path, $fileSize, $source, $mimeType): void {
            $fileId = pathinfo($path, PATHINFO_FILENAME);
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $originalName = Str::limit(Str::slug($source['title'] ?? '') ?: 'import', 120, '').'.'.$extension;

            $file = $this->ingest->attachImportedMaster($record, $fileId, $path, $originalName, $mimeType, $fileSize);

            if ($source['title'] !== null && $record->source_url !== null && $record->title === RecordIngestService::importPlaceholderTitle($record->source_url)) {
                $record->title = Str::limit($source['title'], 250, '');
                $record->save();
            }

            $notes = $this->provenanceNote($record, $source);
            $provenance = $record->provenance()->firstOrNew();

            if ($provenance->notes === null || $provenance->notes === '') {
                $provenance->notes = $notes;
                $provenance->source_carrier ??= SourceCarrier::BornDigital;
                $provenance->save();
            }

            VideoAuditLog::create([
                'record_id' => $record->id,
                'record_file_id' => $file->id,
                'action' => AuditAction::Uploaded,
                'performed_by' => $record->created_by,
                'performed_at' => now(),
                'remarks' => Str::limit($notes, 1000),
            ]);
        });

        $this->recorder->complete($record, PipelineStage::Import);

        StartProcessingJob::dispatch($record, PipelineOptions::fromStored($record->pipeline_options));
    }

    /**
     * @param  array{title: string|null, uploader: string|null, upload_date: string|null, extractor: string|null, webpage_url: string|null}  $source
     */
    private function provenanceNote(Record $record, array $source): string
    {
        $published = null;

        if ($source['upload_date'] !== null) {
            try {
                $published = CarbonImmutable::createFromFormat('Ymd', $source['upload_date'])?->toDateString();
            } catch (Throwable) {
                $published = null;
            }
        }

        return implode(' ', array_filter([
            'Imported from '.($source['webpage_url'] ?? $record->source_url).($source['extractor'] !== null ? " ({$source['extractor']})" : '').'.',
            $source['title'] !== null ? "Title on the site: \"{$source['title']}\"." : null,
            $source['uploader'] !== null ? "Uploaded by {$source['uploader']}".($published !== null ? " on {$published}" : '').'.' : null,
        ]));
    }
}
