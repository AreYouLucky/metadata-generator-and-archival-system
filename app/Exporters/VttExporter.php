<?php

namespace App\Exporters;

use App\Models\Record;

/**
 * WebVTT subtitles, also served to the workspace player as a caption <track>.
 * Speakers become voice spans (`<v Name>`); text is escaped, so a line can
 * never inject markup or end its cue early.
 */
class VttExporter implements Exporter
{
    public function export(Record $record, ExportOptions $options): string
    {
        $cues = ['WEBVTT'];

        foreach (TranscriptLines::for($record, $options->language)['lines'] as $line) {
            $text = self::escape(str_replace('-->', '→', TranscriptLines::cueText($line['text'])));
            $voice = $line['speaker'] !== null ? '<v '.self::escape(str_replace('>', '', $line['speaker'])).'>' : '';

            $cues[] = TranscriptLines::timecode($line['start'], '.').' --> '.TranscriptLines::timecode(max($line['end'], $line['start']), '.')."\n"
                .$voice.$text;
        }

        return implode("\n\n", $cues)."\n";
    }

    public function contentType(): string
    {
        return 'text/vtt';
    }

    public function extension(): string
    {
        return 'vtt';
    }

    private static function escape(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }
}
