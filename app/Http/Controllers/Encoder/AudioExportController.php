<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\ExportAudioRequest;
use App\Http\Requests\Encoder\ShowAudioExportRequest;
use App\Models\Record;
use App\Services\Records\AudioExports;
use App\Services\Records\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The record's audio as MP3 or WAV: made from the master in the background
 * (a few minutes for a long video), then downloaded. Downloads are logged.
 */
class AudioExportController extends Controller
{
    /**
     * Start making the file, unless it exists already (200) or is being made (202).
     */
    public function store(ExportAudioRequest $request, Record $record, AudioExports $exports): JsonResponse
    {
        $status = $exports->start($record, $request->audioFormat());

        return response()->json(['status' => $status, 'exports' => $exports->present($record)], $status === 'ready' ? 200 : 202);
    }

    public function show(ShowAudioExportRequest $request, Record $record, AuditTrail $audit): BinaryFileResponse
    {
        $format = $request->audioFormat();
        $path = AudioExports::path($record, $format);
        $disk = Storage::disk('media');
        abort_unless($disk->exists($path), 404);

        $audit->log($record, AuditAction::Exported, $request->user(), "Format: {$format} (audio)");

        return response()->download(
            $disk->path($path),
            (Str::slug($record->title) ?: 'record').'-'.Str::lower($record->id).".{$format}",
            ['Content-Type' => AudioExports::MIME_TYPES[$format], 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
