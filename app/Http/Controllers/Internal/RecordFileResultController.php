<?php

namespace App\Http\Controllers\Internal;

use App\Enums\AuditAction;
use App\Enums\FileRole;
use App\Events\RecordArchiveUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ReceiveFileResultRequest;
use App\Models\RecordFile;
use App\Services\Records\AuditTrail;
use App\Services\Records\PipelineRecorder;
use Illuminate\Http\JsonResponse;

class RecordFileResultController extends Controller
{
    /**
     * An added file was checksummed or probed, or its check failed (signed
     * by the AI service). Masters go through the pipeline instead.
     */
    public function __invoke(ReceiveFileResultRequest $request, RecordFile $recordFile, string $stage, PipelineRecorder $recorder, AuditTrail $audit): JsonResponse
    {
        abort_if($recordFile->role === FileRole::Master, 404);

        $record = $recordFile->record()->withTrashed()->firstOrFail();

        match ($stage) {
            'checksum' => $recorder->applyChecksums($record, $recordFile, [
                'md5' => (string) $request->validated('md5'),
                'sha256' => (string) $request->validated('sha256'),
                'file_size' => (int) $request->validated('file_size'),
            ]),
            'probe' => $recorder->applyProbe($recordFile, $request->validated()),
            default => $audit->log($record, AuditAction::Updated, null, "Checking the {$recordFile->role->value} file failed: {$request->validated('message')}", $recordFile->id),
        };

        rescue(fn () => RecordArchiveUpdated::dispatch($record, "file-{$stage}"), report: false);

        return response()->json(['ok' => true]);
    }
}
