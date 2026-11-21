<?php

namespace App\Http\Controllers\Encoder;

use App\Exporters\ExportOptions;
use App\Exporters\VttExporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\ShowCaptionsRequest;
use App\Models\Record;
use Symfony\Component\HttpFoundation\Response;

class CaptionController extends Controller
{
    /**
     * WebVTT for the workspace player's <track>, behind the record policy.
     * Served inline and not logged as an export (it's viewing, not a download);
     * not cached, so an edited line shows the next time the track loads.
     */
    public function __invoke(ShowCaptionsRequest $request, Record $record, VttExporter $exporter): Response
    {
        return response($exporter->export($record, new ExportOptions(language: (string) $request->validated('language'))), 200, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'Cache-Control' => 'no-cache, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
