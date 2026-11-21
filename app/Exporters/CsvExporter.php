<?php

namespace App\Exporters;

use App\Models\Record;

/**
 * One row per transcript line: start,end,speaker,text (times in seconds).
 * Cells that a spreadsheet would run as a formula are prefixed with an
 * apostrophe (CSV injection), and the file starts with a UTF-8 byte order
 * mark so Excel reads Filipino text correctly.
 */
class CsvExporter implements Exporter
{
    public function export(Record $record, ExportOptions $options): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fputcsv($stream, ['start', 'end', 'speaker', 'text'], escape: '');

        foreach (TranscriptLines::for($record, $options->language)['lines'] as $line) {
            fputcsv($stream, [
                number_format($line['start'], 3, '.', ''),
                number_format($line['end'], 3, '.', ''),
                self::cell($line['speaker'] ?? ''),
                self::cell($line['text']),
            ], escape: '');
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return "\u{FEFF}".$csv;
    }

    public function contentType(): string
    {
        return 'text/csv';
    }

    public function extension(): string
    {
        return 'csv';
    }

    private static function cell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
