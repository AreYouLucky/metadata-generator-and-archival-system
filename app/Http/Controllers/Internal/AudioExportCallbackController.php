<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ReceiveAudioExportRequest;
use App\Models\Record;
use App\Services\Records\AudioExports;
use Illuminate\Http\JsonResponse;

class AudioExportCallbackController extends Controller
{
    /**
     * An audio export finished or failed (signed by the AI service).
     */
    public function __invoke(ReceiveAudioExportRequest $request, Record $record, AudioExports $exports): JsonResponse
    {
        $error = $request->validated('error');
        $exports->finished($record, (string) $request->validated('format'), is_string($error) ? $error : null);

        return response()->json(['ok' => true]);
    }
}
