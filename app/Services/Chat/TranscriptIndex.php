<?php

namespace App\Services\Chat;

use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordSpeaker;
use App\Models\TranscriptChunk;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a record's transcript passages (`transcript_chunks`) in line with
 * its original transcript. Passages are ~45 s windows cut at line
 * boundaries, with speaker names where known. Rebuilt on demand (the first
 * chat search, and after edits): unchanged passages keep their embedding,
 * changed ones are replaced and embedded again by TranscriptSearch.
 *
 * This replaces the spec's `embed` pipeline stage (see DECISIONS.md).
 */
class TranscriptIndex
{
    public const float WINDOW_SECONDS = 45.0;

    public const float MAX_WINDOW_SECONDS = 60.0;

    public const int MAX_PASSAGE_CHARS = 2500;

    /**
     * Bring the passages up to date. Returns false when the record has no
     * transcript lines (its passages, if any, are removed).
     */
    public function refresh(Record $record): bool
    {
        $transcript = $record->originalTranscript()->first(['id', 'record_id']);

        if ($transcript === null) {
            $record->transcriptChunks()->delete();

            return false;
        }

        $wanted = [];

        foreach ($this->passages($record, $transcript->id) as $passage) {
            $wanted[$passage['text_hash']] = $passage;
        }

        DB::transaction(function () use ($record, $transcript, $wanted): void {
            $existing = $record->transcriptChunks()->get(['id', 'record_transcript_id', 'text_hash']);
            $stale = $existing->filter(fn (TranscriptChunk $chunk): bool => $chunk->record_transcript_id !== $transcript->id
                || ! isset($wanted[$chunk->text_hash]));

            if ($stale->isNotEmpty()) {
                $record->transcriptChunks()->whereKey($stale->modelKeys())->delete();
            }

            $kept = $existing->diff($stale)->pluck('text_hash')->flip();

            foreach ($wanted as $hash => $passage) {
                if (! $kept->has($hash)) {
                    $record->transcriptChunks()->create(['record_transcript_id' => $transcript->id, ...$passage]);
                }
            }
        });

        return $wanted !== [];
    }

    /**
     * @return list<array{start: float, end: float, text: string, text_hash: string}>
     */
    private function passages(Record $record, int $transcriptId): array
    {
        /** @var array<int, string> $names */
        $names = $record->speakers()
            ->get(['id', 'label', 'display_name'])
            ->mapWithKeys(fn (RecordSpeaker $speaker): array => [$speaker->id => $speaker->display_name ?: $speaker->label])
            ->all();

        $passages = [];
        $window = null;

        $segments = RecordSegment::query()
            ->where('record_transcript_id', $transcriptId)
            ->orderBy('position')
            ->select(['id', 'position', 'start', 'end', 'text', 'record_speaker_id'])
            ->cursor();

        foreach ($segments as $segment) {
            $text = trim(preg_replace('/\s+/u', ' ', $segment->text) ?? '');

            if ($text === '') {
                continue;
            }

            if ($window !== null && ($segment->end - $window['start'] > self::MAX_WINDOW_SECONDS
                || $window['chars'] + mb_strlen($text) > self::MAX_PASSAGE_CHARS)) {
                $passages[] = $this->passage($window);
                $window = null;
            }

            $speaker = $segment->record_speaker_id === null ? null : ($names[$segment->record_speaker_id] ?? null);
            $window ??= ['start' => $segment->start, 'end' => $segment->end, 'lines' => [], 'chars' => 0, 'speaker' => null];

            if ($speaker !== null && $speaker !== $window['speaker']) {
                $text = "{$speaker}: {$text}";
            }

            $text = mb_substr($text, 0, self::MAX_PASSAGE_CHARS);
            $window['speaker'] = $speaker;
            $window['end'] = max($window['end'], $segment->end);
            $window['lines'][] = $text;
            $window['chars'] += mb_strlen($text) + 1;

            if ($segment->end - $window['start'] >= self::WINDOW_SECONDS) {
                $passages[] = $this->passage($window);
                $window = null;
            }
        }

        if ($window !== null) {
            $passages[] = $this->passage($window);
        }

        return $passages;
    }

    /**
     * @param  array{start: float, end: float, lines: list<string>, chars: int, speaker: string|null}  $window
     * @return array{start: float, end: float, text: string, text_hash: string}
     */
    private function passage(array $window): array
    {
        $text = implode(' ', $window['lines']);

        return [
            'start' => round($window['start'], 3),
            'end' => round($window['end'], 3),
            'text' => $text,
            'text_hash' => hash('sha256', sprintf('%.3f|%.3f|%s', $window['start'], $window['end'], $text)),
        ];
    }
}
