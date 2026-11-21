<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\UpdateSegmentRequest;
use App\Models\Record;
use App\Models\RecordSegment;
use Illuminate\Http\JsonResponse;

class SegmentController extends Controller
{
    /**
     * Edit one transcript line. `original_text` keeps what transcription produced.
     */
    public function update(UpdateSegmentRequest $request, Record $record, RecordSegment $segment): JsonResponse
    {
        // Never trust the segment id alone: it must belong to this record.
        abort_unless($segment->transcript()->where('record_id', $record->id)->exists(), 404);

        $segment->text = trim((string) $request->validated('text'));
        $segment->is_edited = $segment->text !== $segment->original_text;
        $segment->save();

        return response()->json([
            'id' => $segment->id,
            'text' => $segment->text,
            'is_edited' => $segment->is_edited,
        ]);
    }
}
