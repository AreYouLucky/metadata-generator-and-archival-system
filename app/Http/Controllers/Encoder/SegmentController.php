<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\AssignSpeakerRequest;
use App\Http\Requests\Encoder\DestroySegmentRequest;
use App\Http\Requests\Encoder\UpdateSegmentRequest;
use App\Models\Record;
use App\Models\RecordSegment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SegmentController extends Controller
{
    /**
     * Edit one transcript line's text, speaker and/or timing. `original_text`
     * keeps what transcription produced. New timing on an original line is
     * copied to its translations, which take their timing from it.
     */
    public function update(UpdateSegmentRequest $request, Record $record, RecordSegment $segment): JsonResponse
    {
        self::ensureBelongsTo($segment, $record);
        $validated = $request->validated();

        if (array_key_exists('text', $validated)) {
            $segment->text = trim((string) $validated['text']);
            $segment->is_edited = $segment->text !== $segment->original_text;
        }

        if (array_key_exists('speaker_id', $validated)) {
            $segment->record_speaker_id = $validated['speaker_id'] === null ? null : (int) $validated['speaker_id'];
        }

        $retimed = array_key_exists('start', $validated);

        if ($retimed) {
            $segment->start = round((float) $validated['start'], 3);
            $segment->end = round((float) $validated['end'], 3);
        }

        DB::transaction(function () use ($segment, $record, $retimed): void {
            $segment->save();

            if ($retimed && $segment->transcript->is_original) {
                RecordSegment::query()
                    ->whereIn('record_transcript_id', $record->transcripts()->where('is_original', false)->select('id'))
                    ->where('position', $segment->position)
                    ->update(['start' => $segment->start, 'end' => $segment->end, 'updated_at' => now()]);
            }
        });

        return response()->json([
            'id' => $segment->id,
            'start' => $segment->start,
            'end' => $segment->end,
            'text' => $segment->text,
            'is_edited' => $segment->is_edited,
            'speaker_id' => $segment->record_speaker_id,
        ]);
    }

    /**
     * Give many lines the same speaker in one query ("apply to all"). Only lines
     * of this record's transcripts change, whatever ids were sent.
     */
    public function assignSpeaker(AssignSpeakerRequest $request, Record $record): JsonResponse
    {
        /** @var list<int> $segmentIds */
        $segmentIds = $request->validated('segment_ids');
        $speakerId = $request->validated('speaker_id');

        $updated = RecordSegment::query()
            ->whereIn('record_transcript_id', $record->transcripts()->select('id'))
            ->whereIn('id', $segmentIds)
            ->update([
                'record_speaker_id' => $speakerId === null ? null : (int) $speakerId,
                'updated_at' => now(),
            ]);

        return response()->json(['updated' => $updated]);
    }

    /**
     * Remove a line (music cues, hallucinated text, duplicated lines).
     */
    public function destroy(DestroySegmentRequest $request, Record $record, RecordSegment $segment): Response
    {
        self::ensureBelongsTo($segment, $record);

        $segment->delete();

        return response()->noContent();
    }

    /**
     * Never trust the segment id alone: it must belong to this record. The
     * transcript stays loaded for the caller.
     */
    private static function ensureBelongsTo(RecordSegment $segment, Record $record): void
    {
        $segment->loadMissing('transcript:id,record_id,is_original');

        abort_unless($segment->transcript?->record_id === $record->id, 404);
    }
}
