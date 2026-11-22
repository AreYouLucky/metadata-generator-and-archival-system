<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Exporters\Exporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\ExportRecordRequest;
use App\Models\Record;
use App\Models\VideoAuditLog;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordExportController extends Controller
{
    /**
     * Download the record's metadata or transcript (JSON, SRT, VTT, TXT, CSV,
     * Markdown). Each download is logged. A preview (the Export panel) is
     * returned inline as plain text and is not logged: nothing leaves the
     * workspace.
     */
    public function __invoke(ExportRecordRequest $request, Record $record): Response
    {
        $format = $request->exportFormat();
        /** @var Exporter $exporter */
        $exporter = app($format->exporter());
        $schema = $request->schema();
        $options = $request->exportOptions($schema);
        $content = $exporter->export($record, $options);

        if ($request->boolean('preview')) {
            return response($content, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        VideoAuditLog::create([
            'record_id' => $record->id,
            'record_file_id' => null,
            'action' => AuditAction::Exported,
            'performed_by' => $request->user()?->id,
            'performed_at' => now(),
            'remarks' => implode(', ', array_filter([
                'Format: '.$exporter->extension(),
                $options->language !== null && $format->isTranscript() ? "language: {$options->language}" : null,
                $schema !== null ? "schema: {$schema->name}" : null,
            ])),
        ]);

        $filename = implode('-', array_filter([
            Str::slug($record->title) ?: 'record',
            Str::lower($record->id),
            $format->isTranscript() || $format->value === 'md' ? $options->language : null,
        ])).'.'.$exporter->extension();

        return response($content, 200, [
            'Content-Type' => $exporter->contentType().'; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
