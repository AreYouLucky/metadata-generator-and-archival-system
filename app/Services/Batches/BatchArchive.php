<?php

namespace App\Services\Batches;

use App\Enums\AuditAction;
use App\Enums\BatchZipStatus;
use App\Enums\ExportFormat;
use App\Enums\ProcessingStatus;
use App\Events\BatchProgressUpdated;
use App\Exporters\Exporter;
use App\Exporters\ExportOptions;
use App\Models\Batch;
use App\Models\Record;
use App\Models\RecordTranscript;
use App\Models\VideoAuditLog;
use App\Services\Catalogue\Distribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * The batch ZIP (GET /batches/{batch}/download): per record a folder with the
 * JSON, Markdown and, when there is a transcript, SRT, VTT, TXT and CSV
 * exports, optionally the thumbnail, plus a manifest listing every record and
 * why any was left out.
 *
 * Owner's rule (DECISIONS.md, Phase 6): the ZIP is the encoder's working
 * export, so drafts are included, but the archive's restrictions apply: a
 * deleted, classified, embargoed or rights-restricted record is left out
 * (the distribution rule without its approval check). Release forms and
 * other attachments are never included. Records still processing are left
 * out until they finish.
 */
class BatchArchive
{
    /**
     * Every format, in the order the folder lists them.
     */
    private const array FORMATS = [ExportFormat::Json, ExportFormat::Markdown, ExportFormat::Srt, ExportFormat::Vtt, ExportFormat::Txt, ExportFormat::Csv];

    public function __construct(private Distribution $distribution) {}

    /**
     * Why a record stays out of the ZIP; empty when it goes in.
     *
     * @return list<string>
     */
    public function exclusions(Record $record): array
    {
        if (in_array($record->processing_status, [ProcessingStatus::Queued, ProcessingStatus::Processing], true)) {
            return ['It is still processing.'];
        }

        return $this->distribution->blockers($record, ignoreApproval: true);
    }

    /**
     * Write the ZIP to the media disk (replacing the previous one) and mark
     * the batch's ZIP ready. Each record included is logged as `exported`.
     */
    public function build(Batch $batch, bool $thumbnails, ?int $userId): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'batch-zip-');

        if ($temporary === false) {
            throw new RuntimeException('Could not create a temporary file for the ZIP.');
        }

        try {
            $included = $this->write($batch, $temporary, $thumbnails);

            $path = "batches/{$batch->id}/".Str::slug($batch->label()).'-'.now()->format('Ymd-His').'.zip';
            $stream = fopen($temporary, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Could not read the finished ZIP.');
            }

            Storage::disk('media')->writeStream($path, $stream);
            is_resource($stream) && fclose($stream);
        } finally {
            @unlink($temporary);
        }

        $previous = $batch->zip_path;

        DB::transaction(function () use ($batch, $path, $included, $userId): void {
            $batch->zip_path = $path;
            $batch->zip_status = BatchZipStatus::Ready;
            $batch->zip_built_at = now();
            $batch->save();

            foreach ($included as $recordId) {
                VideoAuditLog::create([
                    'record_id' => $recordId,
                    'record_file_id' => null,
                    'action' => AuditAction::Exported,
                    'performed_by' => $userId,
                    'performed_at' => now(),
                    'remarks' => "Format: zip (batch {$batch->id})",
                ]);
            }
        });

        if ($previous !== null && $previous !== $path) {
            Storage::disk('media')->delete($previous);
        }

        rescue(fn () => BatchProgressUpdated::dispatch($batch), report: false);
    }

    /**
     * @return list<string> ids of the records written into the ZIP
     */
    private function write(Batch $batch, string $target, bool $thumbnails): array
    {
        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP.');
        }

        $manifest = [['accession_number', 'title', 'folder', 'included', 'reason']];
        $included = [];
        $folders = [];

        $records = $batch->records()
            ->with('rights')
            ->orderBy('created_at')
            ->get();

        // Which records have a transcript, for the subtitle and text formats (one query).
        $transcribed = RecordTranscript::query()
            ->whereIn('record_id', $records->modelKeys())
            ->where('is_original', true)
            ->pluck('record_id')
            ->flip();

        foreach ($records as $record) {
            $folder = $this->folderName($record, $folders);
            $reasons = $this->exclusions($record);

            if ($reasons !== []) {
                $manifest[] = [$record->accession_number, $record->title, '', 'no', implode(' ', $reasons)];

                continue;
            }

            foreach (self::FORMATS as $format) {
                if ($format->isTranscript() && ! $transcribed->has($record->id)) {
                    continue;
                }

                /** @var Exporter $exporter */
                $exporter = app($format->exporter());
                $zip->addFromString("{$folder}/{$folder}.{$exporter->extension()}", $exporter->export($record, new ExportOptions));
            }

            if ($thumbnails && $record->thumbnail_path !== null && Storage::disk('media')->exists($record->thumbnail_path)) {
                $extension = pathinfo($record->thumbnail_path, PATHINFO_EXTENSION) ?: 'jpg';
                $zip->addFromString("{$folder}/thumbnail.{$extension}", (string) Storage::disk('media')->get($record->thumbnail_path));
            }

            $manifest[] = [$record->accession_number, $record->title, $folder, 'yes', ''];
            $included[] = $record->id;
        }

        $zip->addFromString('manifest.csv', self::csv($manifest));
        $zip->close();

        return $included;
    }

    /**
     * `{accession}-{title}`, unique inside the ZIP.
     *
     * @param  array<string, true>  $taken
     */
    private function folderName(Record $record, array &$taken): string
    {
        $base = Str::limit(Str::slug(trim(($record->accession_number ?? '').' '.$record->title)) ?: Str::lower($record->id), 80, '');
        $name = $base;

        for ($suffix = 2; isset($taken[$name]); $suffix++) {
            $name = "{$base}-{$suffix}";
        }

        $taken[$name] = true;

        return $name;
    }

    /**
     * Cells starting with = + - @ get a leading quote, so spreadsheets don't run them.
     *
     * @param  list<list<string|null>>  $rows
     */
    private static function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Could not write the manifest.');
        }

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                fn (?string $cell): string => preg_match('/^[=+\-@]/', (string) $cell) === 1 ? "'".$cell : (string) $cell,
                $row,
            ), escape: '');
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
