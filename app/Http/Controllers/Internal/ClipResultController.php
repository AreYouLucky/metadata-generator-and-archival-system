<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ReceiveClipResultRequest;
use App\Models\Clip;
use App\Models\Record;
use App\Services\Records\ClipCutter;
use Illuminate\Http\JsonResponse;

class ClipResultController extends Controller
{
    /**
     * A clip was cut and checked, or failed (signed by the AI service). A
     * result for a clip that already finished is ignored (callbacks retry).
     */
    public function __invoke(ReceiveClipResultRequest $request, Record $record, Clip $clip, ClipCutter $cutter): JsonResponse
    {
        if (! $clip->isBusy()) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $error = $request->validated('error');

        if (is_string($error) && $error !== '') {
            $cutter->failed($clip, $error);
        } else {
            $probe = $request->validated('probe');
            $cutter->done(
                $clip,
                (int) $request->validated('file_size'),
                (string) $request->validated('md5'),
                (string) $request->validated('sha256'),
                is_array($probe) ? $probe : [],
            );
        }

        return response()->json(['ok' => true]);
    }
}
