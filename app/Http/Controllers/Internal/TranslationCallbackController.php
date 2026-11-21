<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ReceiveTranslationProgressRequest;
use App\Http\Requests\Internal\ReceiveTranslationResultRequest;
use App\Models\Record;
use App\Models\RecordTranscript;
use App\Services\Records\TranslationRecorder;
use Illuminate\Http\JsonResponse;

/**
 * Signed callbacks for a translation job. {transcript} is scoped to the
 * record, and must be a translation: the original can't be overwritten here.
 */
class TranslationCallbackController extends Controller
{
    public function progress(ReceiveTranslationProgressRequest $request, Record $record, RecordTranscript $transcript, TranslationRecorder $translations): JsonResponse
    {
        abort_if($transcript->is_original, 404);

        if ($request->validated('status') === 'failed') {
            $translations->fail($record, $transcript, (string) ($request->validated('message') ?? 'The translation failed.'));
        } else {
            $translations->progress($record, $transcript, (int) $request->validated('progress'));
        }

        return response()->json(['ok' => true]);
    }

    public function result(ReceiveTranslationResultRequest $request, Record $record, RecordTranscript $transcript, TranslationRecorder $translations): JsonResponse
    {
        abort_if($transcript->is_original, 404);

        $translations->complete($record, $transcript, $request->lines(), $request->usage());

        return response()->json(['ok' => true]);
    }
}
