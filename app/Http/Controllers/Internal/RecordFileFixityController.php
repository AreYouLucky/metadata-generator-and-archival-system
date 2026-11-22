<?php

namespace App\Http\Controllers\Internal;

use App\Enums\FixityTrigger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ReceiveFixityRequest;
use App\Models\RecordFile;
use App\Services\Records\FixityRecorder;
use Illuminate\Http\JsonResponse;

class RecordFileFixityController extends Controller
{
    /**
     * A fixity check finished (signed by the AI service).
     */
    public function __invoke(ReceiveFixityRequest $request, RecordFile $recordFile, FixityRecorder $fixity): JsonResponse
    {
        $matched = $fixity->record($recordFile, FixityTrigger::from((string) $request->validated('triggered_by')), [
            'md5' => $request->validated('md5'),
            'sha256' => $request->validated('sha256'),
            'error' => $request->validated('error'),
        ]);

        return response()->json(['ok' => true, 'matched' => $matched]);
    }
}
