<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Exporters\Exporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExportRecordRequest;
use App\Models\Record;
use App\Models\User;
use App\Services\Records\AuditTrail;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/v1/records/{record}/export: the same exports as the workspace
 * (JSON, SRT, VTT, TXT, CSV, Markdown), only for records that may leave the
 * archive, without release forms or unconfirmed suggestions. Each export is
 * logged as `exported` with "via API".
 */
class RecordExportController extends Controller
{
    public function __invoke(ExportRecordRequest $request, Record $record, AuditTrail $audit): Response
    {
        $format = $request->exportFormat();
        /** @var Exporter $exporter */
        $exporter = app($format->exporter());
        $schema = $request->schema();
        $options = $request->exportOptions($schema);
        $content = $exporter->export($record, $options);
        /** @var User $user */
        $user = $request->user();

        $audit->log($record, AuditAction::Exported, $user, implode(', ', array_filter([
            'Format: '.$exporter->extension(),
            $options->language !== null && $format->isTranscript() ? "language: {$options->language}" : null,
            $schema !== null ? "schema: {$schema->name}" : null,
            'via API',
        ])));

        return response($content, 200, [
            'Content-Type' => $exporter->contentType().'; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
