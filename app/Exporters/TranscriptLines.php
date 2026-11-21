<?php

namespace App\Exporters;

use App\Enums\TaskStatus;
use App\Models\Record;
use App\Models\RecordSpeaker;
use App\Models\RecordTranscript;

/**
 * The timed lines of one transcript (the original, or a finished translation)
 * with speaker names, as the subtitle and text exporters need them. Only the
 * columns they use are read, so a three-hour transcript stays cheap.
 */
final class TranscriptLines
{
    /**
     * @return array{language: string|null, lines: list<array{start: float, end: float, speaker: string|null, text: string}>}
     */
    public static function for(Record $record, ?string $language = null): array
    {
        $transcript = self::transcript($record, $language);

        if ($transcript === null) {
            return ['language' => $language, 'lines' => []];
        }

        $speakers = $record->speakers()
            ->get(['id', 'label', 'display_name'])
            ->mapWithKeys(fn (RecordSpeaker $speaker): array => [$speaker->id => $speaker->display_name ?? $speaker->label]);

        $lines = [];

        foreach ($transcript->segments()->toBase()->get(['start', 'end', 'text', 'record_speaker_id']) as $row) {
            $text = trim((string) $row->text);

            if ($text === '') {
                continue;
            }

            $lines[] = [
                'start' => (float) $row->start,
                'end' => (float) $row->end,
                'speaker' => $row->record_speaker_id === null ? null : $speakers->get($row->record_speaker_id),
                'text' => $text,
            ];
        }

        return ['language' => $transcript->language, 'lines' => $lines];
    }

    private static function transcript(Record $record, ?string $language): ?RecordTranscript
    {
        $query = $record->transcripts()->select(['id', 'record_id', 'language']);

        return $language === null
            ? $query->where('is_original', true)->first()
            : $query->where('language', $language)->where('status', TaskStatus::Done)->first();
    }

    /**
     * "00:01:02,500" (SRT) or "00:01:02.500" (WebVTT).
     */
    public static function timecode(float $seconds, string $separator): string
    {
        $milliseconds = (int) round(max(0, $seconds) * 1000);
        $hours = intdiv($milliseconds, 3_600_000);
        $minutes = intdiv($milliseconds % 3_600_000, 60_000);
        $secs = intdiv($milliseconds % 60_000, 1000);

        return sprintf('%02d:%02d:%02d%s%03d', $hours, $minutes, $secs, $separator, $milliseconds % 1000);
    }

    /**
     * "1:02" or "1:02:03", as the workspace shows times.
     */
    public static function clock(float $seconds): string
    {
        $total = (int) floor(max(0, $seconds));
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $secs = $total % 60;

        return $hours > 0 ? sprintf('%d:%02d:%02d', $hours, $minutes, $secs) : sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * One subtitle cue's text: no blank lines (a blank line ends a cue).
     */
    public static function cueText(string $text): string
    {
        return implode("\n", array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), fn (string $line): bool => $line !== ''));
    }
}
