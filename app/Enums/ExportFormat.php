<?php

namespace App\Enums;

use App\Exporters\CsvExporter;
use App\Exporters\Exporter;
use App\Exporters\JsonExporter;
use App\Exporters\MarkdownExporter;
use App\Exporters\SrtExporter;
use App\Exporters\TxtExporter;
use App\Exporters\VttExporter;

/**
 * Download formats of GET /records/{record}/export (PROJECT_SPEC §8, Exports).
 */
enum ExportFormat: string
{
    case Json = 'json';
    case Srt = 'srt';
    case Vtt = 'vtt';
    case Txt = 'txt';
    case Csv = 'csv';
    case Markdown = 'md';

    /**
     * @return class-string<Exporter>
     */
    public function exporter(): string
    {
        return match ($this) {
            self::Json => JsonExporter::class,
            self::Srt => SrtExporter::class,
            self::Vtt => VttExporter::class,
            self::Txt => TxtExporter::class,
            self::Csv => CsvExporter::class,
            self::Markdown => MarkdownExporter::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Json => 'JSON (full record)',
            self::Srt => 'SRT subtitles',
            self::Vtt => 'WebVTT subtitles',
            self::Txt => 'Plain text transcript',
            self::Csv => 'CSV (one row per line)',
            self::Markdown => 'Markdown summary',
        };
    }

    /**
     * Formats built from one transcript, so they take a language (original or translation).
     */
    public function isTranscript(): bool
    {
        return in_array($this, [self::Srt, self::Vtt, self::Txt, self::Csv], true);
    }

    /**
     * @return list<array{value: string, label: string, transcript: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $format): array => [
            'value' => $format->value,
            'label' => $format->label(),
            'transcript' => $format->isTranscript(),
        ], self::cases());
    }
}
