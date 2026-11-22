<?php

namespace App\Exporters;

use App\Models\Record;

/**
 * SubRip subtitles from the original transcript or a translation, with a
 * "Speaker: " prefix when speakers are known.
 */
class SrtExporter implements Exporter
{
    public function export(Record $record, ExportOptions $options): string
    {
        $cues = [];

        foreach (TranscriptLines::for($record, $options->language)['lines'] as $index => $line) {
            $text = TranscriptLines::cueText($line['text']);

            $cues[] = ($index + 1)."\n"
                .TranscriptLines::timecode($line['start'], ',').' --> '.TranscriptLines::timecode(max($line['end'], $line['start']), ',')."\n"
                .($line['speaker'] !== null ? "{$line['speaker']}: " : '').$text;
        }

        return $cues === [] ? '' : implode("\n\n", $cues)."\n";
    }

    public function contentType(): string
    {
        return 'application/x-subrip';
    }

    public function extension(): string
    {
        return 'srt';
    }
}
