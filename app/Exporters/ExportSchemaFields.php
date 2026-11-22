<?php

namespace App\Exporters;

use Illuminate\Support\Arr;

/**
 * The dot-paths a custom export schema may keep from the JSON document
 * (PROJECT_SPEC §10), grouped for the schema builder. A schema is validated
 * against this list, so a saved schema can only ever name known blocks.
 */
final class ExportSchemaFields
{
    /**
     * Group label => [path => label]. The order is the order of the JSON output.
     *
     * @var array<string, array<string, string>>
     */
    public const array GROUPS = [
        'Record' => [
            'record' => 'Whole record block',
            'record.id' => 'ID',
            'record.accessionNumber' => 'Accession number',
            'record.title' => 'Title',
            'record.alternativeTitle' => 'Alternative title',
            'record.project' => 'Project',
            'record.series' => 'Series',
            'record.work' => 'Work (reels / parts)',
            'record.agency' => 'Agency',
            'record.productionUnit' => 'Production unit',
            'record.isApproved' => 'Approved',
            'record.reviewStatus' => 'Review status',
            'record.publishedAt' => 'Published at',
        ],
        'Descriptive' => [
            'descriptive' => 'Whole descriptive block',
            'descriptive.description' => 'Description',
            'descriptive.genre' => 'Genre',
            'descriptive.videoType' => 'Video type',
            'descriptive.language' => 'Language',
            'descriptive.countryOfOrigin' => 'Country of origin',
            'descriptive.dateRecorded' => 'Date recorded',
            'descriptive.datePublished' => 'Date published',
            'descriptive.season' => 'Season',
            'descriptive.episode' => 'Episode',
            'descriptive.location' => 'Location',
            'descriptive.subjects' => 'Subjects',
            'descriptive.keywords' => 'Keywords',
        ],
        'Archival' => [
            'contributors' => 'Credits',
            'technical' => 'Technical (files, codecs, checksums)',
            'rights' => 'Rights',
            'archival' => 'Classification and retention',
            'provenance' => 'Provenance (audit log, fixity)',
            'structure' => 'Structure (files, attachments, transcripts)',
        ],
        'Transcript' => [
            'speakers' => 'Speakers',
            'transcript' => 'Transcript lines',
            'translations' => 'Translations',
        ],
        'AI metadata' => [
            'ai' => 'Whole AI block',
            'ai.preset' => 'Preset',
            'ai.model' => 'Model',
            'ai.customPrompt' => 'Custom prompt',
            'ai.titles' => 'Title options',
            'ai.selectedTitle' => 'Selected title',
            'ai.description' => 'Description',
            'ai.summary' => 'Summary',
            'ai.tags' => 'Tags',
            'ai.keywords' => 'Keywords',
            'ai.hashtags' => 'Hashtags',
            'ai.chapters' => 'Chapters',
            'ai.quotes' => 'Quotes',
            'ai.tone' => 'Tone',
            'ai.contentFlags' => 'Content flags',
        ],
        'Later phases' => [
            'visual' => 'Visual analysis',
            'clips' => 'Clips',
            'publishing' => 'Publishing',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        return array_keys(array_merge(...array_values(self::GROUPS)));
    }

    /**
     * For the schema builder: [{group, fields: [{path, label}]}].
     *
     * @return list<array{group: string, fields: list<array{path: string, label: string}>}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::GROUPS as $group => $fields) {
            $options[] = [
                'group' => $group,
                'fields' => array_map(
                    fn (string $path, string $label): array => ['path' => $path, 'label' => $label],
                    array_keys($fields),
                    array_values($fields),
                ),
            ];
        }

        return $options;
    }

    /**
     * Keep only the chosen paths, in document order. `schemaVersion` and
     * `generatedAt` always stay, so a consumer can tell which shape it got.
     * A chosen sub-field of a block that is null (e.g. `ai.tags` before any
     * metadata exists) keeps the block as null rather than vanishing.
     *
     * @param  array<string, mixed>  $document
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public static function filter(array $document, array $fields): array
    {
        $result = [
            'schemaVersion' => $document['schemaVersion'] ?? null,
            'generatedAt' => $document['generatedAt'] ?? null,
        ];
        $chosen = array_flip($fields);

        foreach (self::paths() as $path) {
            if (! isset($chosen[$path])) {
                continue;
            }

            if (Arr::has($document, $path)) {
                Arr::set($result, $path, Arr::get($document, $path));

                continue;
            }

            $block = explode('.', $path, 2)[0];

            if (array_key_exists($block, $document) && $document[$block] === null && ! array_key_exists($block, $result)) {
                $result[$block] = null;
            }
        }

        return $result;
    }
}
