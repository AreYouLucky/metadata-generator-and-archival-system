<?php

namespace App\Exporters;

use App\Models\Record;

/**
 * The transcript as plain text: one line per segment, optionally with its
 * start time, and "Speaker: " when speakers are known.
 */
class TxtExporter implements Exporter
{
    public function export(Record $record, ExportOptions $options): string
    {
        $lines = array_map(
            fn (array $line): string => ($options->timestamps ? '['.TranscriptLines::clock($line['start']).'] ' : '')
                .($line['speaker'] !== null ? "{$line['speaker']}: " : '')
                .preg_replace('/\s+/u', ' ', $line['text']),
            TranscriptLines::for($record, $options->language)['lines'],
        );

        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }

    public function contentType(): string
    {
        return 'text/plain';
    }

    public function extension(): string
    {
        return 'txt';
    }
}
