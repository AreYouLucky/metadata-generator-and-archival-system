<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Exporters\ExportOptions;
use App\Exporters\JsonExporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\ExportRecordRequest;
use App\Models\Record;
use App\Models\VideoAuditLog;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordExportController extends Controller
{
    /**
     * Download the record's metadata (JSON in Phase 1). Each export is logged.
     */
    public function __invoke(ExportRecordRequest $request, Record $record, JsonExporter $exporter): Response
    {
        $content = $exporter->export($record, new ExportOptions);

        VideoAuditLog::create([
            'record_id' => $record->id,
            'record_file_id' => null,
            'action' => AuditAction::Exported,
            'performed_by' => $request->user()?->id,
            'performed_at' => now(),
            'remarks' => 'Format: '.$exporter->extension(),
        ]);

        $filename = (Str::slug($record->title) ?: 'record').'-'.Str::lower($record->id).'.'.$exporter->extension();

        return response($content, 200, [
            'Content-Type' => $exporter->contentType().'; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
