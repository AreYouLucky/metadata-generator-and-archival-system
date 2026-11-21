<?php

namespace App\Exporters;

use App\Models\Record;
use App\Models\RecordContributor;
use App\Models\RecordRights;

/**
 * A readable summary for sharing (PROJECT_SPEC §8): title, description,
 * credits, chapters, tags, quotes and the transcript, plus a rights summary
 * (copyright, licence, restrictions, embargo) when rights exist. Text from
 * the record is escaped so it can't turn into links, images or HTML.
 */
class MarkdownExporter implements Exporter
{
    public function export(Record $record, ExportOptions $options): string
    {
        $record->loadMissing([
            'project:id,title',
            'projectSeries:id,title',
            'agency:id,name,acronym',
            'contributors',
            'rights',
            'activeMetadata',
        ]);

        /** @var array<string, mixed> $ai */
        $ai = $record->activeMetadata->payload ?? [];
        $title = $record->title !== '' ? $record->title : (string) ($ai['selectedTitle'] ?? 'Untitled record');
        $description = $record->description ?? ($ai['description'] ?? null);

        $sections = ['# '.self::escape($title)];

        $context = array_filter([
            $record->project ? '**Project:** '.self::escape($record->project->title) : null,
            $record->projectSeries ? '**Series:** '.self::escape($record->projectSeries->title) : null,
            $record->agency ? '**Agency:** '.self::escape($record->agency->name) : null,
            $record->language ? '**Language:** '.self::escape($record->language) : null,
        ]);

        if ($context !== []) {
            $sections[] = implode('  '."\n", $context);
        }

        if (is_string($description) && trim($description) !== '') {
            $sections[] = "## Description\n\n".self::escape(trim($description));
        }

        if (is_string($ai['summary'] ?? null) && trim($ai['summary']) !== '') {
            $sections[] = "## Summary\n\n".self::escape(trim($ai['summary']));
        }

        if ($record->contributors->isNotEmpty()) {
            $sections[] = "## Credits\n\n".$record->contributors
                ->map(fn (RecordContributor $credit): string => "- **{$credit->role->label()}:** ".self::escape($credit->name))
                ->implode("\n");
        }

        $sections = [...$sections, ...self::aiSections($ai)];

        if ($record->rights !== null) {
            $sections[] = self::rights($record->rights);
        }

        $transcript = TranscriptLines::for($record, $options->language)['lines'];

        if ($transcript !== []) {
            $sections[] = "## Transcript\n\n".implode("\n\n", array_map(
                fn (array $line): string => '**['.TranscriptLines::clock($line['start']).']** '
                    .($line['speaker'] !== null ? self::escape($line['speaker']).': ' : '')
                    .self::escape(preg_replace('/\s+/u', ' ', $line['text']) ?? ''),
                $transcript,
            ));
        }

        return implode("\n\n", $sections)."\n";
    }

    public function contentType(): string
    {
        return 'text/markdown';
    }

    public function extension(): string
    {
        return 'md';
    }

    /**
     * @param  array<string, mixed>  $ai
     * @return list<string>
     */
    private static function aiSections(array $ai): array
    {
        $sections = [];

        /** @var list<array{start: float|int, title: string}> $chapters */
        $chapters = is_array($ai['chapters'] ?? null) ? $ai['chapters'] : [];

        if ($chapters !== []) {
            $sections[] = "## Chapters\n\n".implode("\n", array_map(
                fn (array $chapter): string => '- `'.TranscriptLines::clock((float) $chapter['start']).'` '.self::escape($chapter['title']),
                $chapters,
            ));
        }

        /** @var list<string> $tags */
        $tags = is_array($ai['tags'] ?? null) ? $ai['tags'] : [];
        /** @var list<string> $hashtags */
        $hashtags = is_array($ai['hashtags'] ?? null) ? $ai['hashtags'] : [];

        if ($tags !== [] || $hashtags !== []) {
            $sections[] = "## Tags\n\n".implode("\n\n", array_filter([
                $tags === [] ? null : self::escape(implode(', ', $tags)),
                $hashtags === [] ? null : self::escape(implode(' ', $hashtags)),
            ]));
        }

        /** @var list<array{start: float|int, text: string}> $quotes */
        $quotes = is_array($ai['quotes'] ?? null) ? $ai['quotes'] : [];

        if ($quotes !== []) {
            $sections[] = "## Quotes\n\n".implode("\n\n", array_map(
                fn (array $quote): string => '> '.self::escape($quote['text']).' — `'.TranscriptLines::clock((float) $quote['start']).'`',
                $quotes,
            ));
        }

        return $sections;
    }

    private static function rights(RecordRights $rights): string
    {
        $copyright = trim(implode(' ', array_filter([
            $rights->copyright_year !== null ? (string) $rights->copyright_year : null,
            $rights->copyright_holder,
        ])));
        $license = $rights->license !== null
            ? self::escape($rights->license).($rights->license_url !== null ? ' (<'.str_replace(['<', '>', ' '], '', $rights->license_url).'>)' : '')
            : null;

        $lines = array_filter([
            $copyright !== '' ? '- **Copyright:** © '.self::escape($copyright) : null,
            $rights->rights_statement !== null ? '- **Rights statement:** '.self::escape($rights->rights_statement) : null,
            $license !== null ? '- **Licence:** '.$license : null,
            '- **Access:** '.$rights->access_level->value,
            $rights->usage_restrictions !== null ? '- **Restrictions:** '.self::escape($rights->usage_restrictions) : null,
            $rights->embargo_until !== null ? '- **Embargo until:** '.$rights->embargo_until->format('Y-m-d') : null,
        ]);

        return "## Rights\n\n".implode("\n", $lines);
    }

    /**
     * Escape the characters Markdown treats as formatting or HTML.
     */
    private static function escape(string $text): string
    {
        return preg_replace('/([\\\\`*_{}\[\]<>#|!])/u', '\\\\$1', $text) ?? $text;
    }
}
