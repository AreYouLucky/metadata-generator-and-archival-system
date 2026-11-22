<?php

namespace App\Services\Records;

use App\Enums\TaskStatus;
use App\Events\RecordTranslationUpdated;
use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use Illuminate\Support\Facades\DB;

/**
 * Translation state: a `record_transcripts` row that is not the original,
 * moving queued -> processing -> done | failed. Every change is broadcast on
 * the record's channel (with rescue(), so Reverb being down never fails a
 * request or callback).
 */
class TranslationRecorder
{
    private const int SEGMENT_INSERT_BATCH = 500;

    public function __construct(private PipelineRecorder $pipeline) {}

    /**
     * Create the translation (or reset a finished/failed one) as queued.
     */
    public function queue(Record $record, string $language): RecordTranscript
    {
        $translation = $record->transcripts()->firstOrNew(['language' => $language], ['is_original' => false]);
        $translation->status = TaskStatus::Queued;
        $translation->progress = 0;
        $translation->error = null;
        $translation->save();

        $this->broadcast($record, $translation);

        return $translation;
    }

    public function progress(Record $record, RecordTranscript $translation, int $progress): void
    {
        // A late tick must not undo a result that already arrived.
        if (! $translation->isBusy()) {
            return;
        }

        $translation->status = TaskStatus::Processing;
        $translation->progress = max(0, min(99, $progress));
        $translation->save();

        $this->broadcast($record, $translation);
    }

    public function fail(Record $record, RecordTranscript $translation, string $error): void
    {
        $translation->status = TaskStatus::Failed;
        $translation->error = mb_substr($error, 0, 1000);
        $translation->save();

        $this->broadcast($record, $translation);
    }

    /**
     * Store the translated lines. Each takes its timing, order and speaker from
     * the original line with the same id; lines whose original was deleted in
     * the meantime are dropped.
     *
     * @param  list<array{id: int, text: string}>  $lines
     * @param  list<array{kind: string, quantity: float, estimated_cost?: float|null}>  $usage
     */
    public function complete(Record $record, RecordTranscript $translation, array $lines, array $usage): void
    {
        $original = $record->originalTranscript()->first(['id', 'record_id']);

        DB::transaction(function () use ($record, $translation, $lines, $usage, $original): void {
            $translation->segments()->delete();

            $sources = $original === null ? collect() : $original->segments()
                ->whereIn('id', array_column($lines, 'id'))
                ->get(['id', 'position', 'start', 'end', 'record_speaker_id'])
                ->keyBy('id');

            $now = now();
            $rows = [];

            foreach ($lines as $line) {
                /** @var RecordSegment|null $source */
                $source = $sources->get($line['id']);

                if ($source === null) {
                    continue;
                }

                $rows[] = [
                    'record_transcript_id' => $translation->id,
                    'position' => $source->position,
                    'start' => $source->start,
                    'end' => $source->end,
                    'text' => $line['text'],
                    'original_text' => $line['text'],
                    'record_speaker_id' => $source->record_speaker_id,
                    'is_edited' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, self::SEGMENT_INSERT_BATCH) as $batch) {
                DB::table('record_segments')->insert($batch);
            }

            $translation->status = TaskStatus::Done;
            $translation->progress = 100;
            $translation->error = null;
            $translation->save();

            $this->pipeline->recordUsage($record, $usage);
        });

        $this->broadcast($record, $translation);
    }

    private function broadcast(Record $record, RecordTranscript $translation): void
    {
        rescue(fn () => RecordTranslationUpdated::dispatch($record, $translation), report: false);
    }
}
