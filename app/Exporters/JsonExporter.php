<?php

namespace App\Exporters;

use App\Enums\ReviewStatus;
use App\Enums\TaskStatus;
use App\Models\FixityCheck;
use App\Models\Record;
use App\Models\RecordAttachment;
use App\Models\RecordContributor;
use App\Models\RecordFile;
use App\Models\RecordKeyword;
use App\Models\RecordScene;
use App\Models\RecordSegment;
use App\Models\RecordSpeaker;
use App\Models\RecordSubject;
use App\Models\RecordTranscript;
use App\Models\VideoAuditLog;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use stdClass;

/**
 * The full catalogue record as JSON (PROJECT_SPEC §10). Blocks that later
 * phases fill (rights, provenance, visual, clips...) are present with nulls or
 * empty lists, so the shape stays stable across phases. Times are seconds.
 */
class JsonExporter implements Exporter
{
    public const string SCHEMA_VERSION = '1.0';

    public const string GENERATOR_VERSION = '0.3.0';

    /**
     * The whole document, or only a custom schema's fields when `fields` is set.
     */
    public function export(Record $record, ExportOptions $options): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;
        $document = $this->document($record);

        if ($options->fields !== null) {
            $document = ExportSchemaFields::filter($document, $options->fields);
        }

        return json_encode($document, $options->pretty ? $flags | JSON_PRETTY_PRINT : $flags);
    }

    public function contentType(): string
    {
        return 'application/json';
    }

    public function extension(): string
    {
        return 'json';
    }

    /**
     * @return array<string, mixed>
     */
    public function document(Record $record): array
    {
        $record->loadMissing([
            'project:id,title,date,end_date',
            'projectSeries:id,title,date,end_date',
            'work:id,title,total_parts',
            'agency:id,name,acronym',
            'division:id,name,acronym',
            'genre:id,name',
            'videoType:id,name',
            'region:id,name',
            'province:id,name',
            'city:id,name',
            'barangay:id,name',
            'subjects',
            'keywords',
            'contributors',
            'files.technicalMetadata',
            'files.fixityChecks',
            'rights',
            'provenance',
            'auditLogs.performer:id,name',
            'attachments',
            'transcripts' => fn ($query) => $query->where('status', TaskStatus::Done),
            'transcripts.segments.speaker:id,label',
            'speakers',
            'scenes',
            'activeMetadata',
        ]);

        $original = $record->transcripts->firstWhere('is_original', true);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'generatedAt' => now()->utc()->toIso8601ZuluString(),
            'generator' => ['app' => 'metadata-generator-and-archival-system', 'version' => self::GENERATOR_VERSION],
            'record' => $this->recordBlock($record),
            'descriptive' => $this->descriptive($record),
            'contributors' => $record->contributors->map(fn (RecordContributor $contributor): array => [
                'role' => $contributor->role->value,
                'name' => $contributor->name,
            ])->values()->all(),
            'technical' => ['files' => $record->files->map(fn (RecordFile $file): array => $this->technicalFile($file))->values()->all()],
            'rights' => $this->rights($record),
            'archival' => [
                'securityClassification' => $record->security_classification->value,
                'retentionPeriod' => $record->retention_period,
                'archivalValue' => $record->archival_value?->value,
                'disposition' => ['date' => $this->date($record->disposition_date), 'action' => $record->disposition_action?->value],
            ],
            'provenance' => $this->provenance($record),
            'structure' => [
                'files' => $record->files->map(fn (RecordFile $file): array => ['fileId' => $file->id, 'role' => $file->role->value])->values()->all(),
                'attachments' => $record->attachments->map(fn (RecordAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'kind' => $attachment->kind->value,
                    'name' => $attachment->original_filename,
                    'sha256' => $attachment->sha256_checksum,
                ])->values()->all(),
                'transcripts' => $record->transcripts->map(fn (RecordTranscript $transcript): array => [
                    'language' => $transcript->language,
                    'original' => $transcript->is_original,
                ])->values()->all(),
                'segments' => $record->scenes->map(fn (RecordScene $scene): array => [
                    'kind' => $scene->kind->value,
                    'start' => $scene->start,
                    'end' => $scene->end,
                    'tcIn' => $scene->tc_in,
                    'tcOut' => $scene->tc_out,
                    'label' => $scene->label,
                ])->values()->all(),
            ],
            'speakers' => $record->speakers->map(fn (RecordSpeaker $speaker): array => [
                'id' => $speaker->label,
                'name' => $speaker->display_name,
            ])->values()->all(),
            'transcript' => $original ? $this->segments($original, withSpeakers: true) : [],
            'translations' => $record->transcripts
                ->where('is_original', false)
                ->mapWithKeys(fn (RecordTranscript $transcript): array => [$transcript->language => $this->segments($transcript, withSpeakers: false)])
                ->all() ?: new stdClass,
            'ai' => $this->ai($record),
            'visual' => ['thumbnail' => null, 'frames' => []],
            'clips' => [],
            'publishing' => ['youtube' => ['videoId' => null, 'publishedAt' => null]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recordBlock(Record $record): array
    {
        return [
            'id' => $record->id,
            'accessionNumber' => $record->accession_number,
            'title' => $record->title,
            'alternativeTitle' => $record->alternative_title,
            'project' => $record->project ? [
                'id' => $record->project->id,
                'title' => $record->project->title,
                'date' => $this->date($record->project->date),
                'endDate' => $this->date($record->project->end_date),
            ] : null,
            'series' => $record->projectSeries ? [
                'id' => $record->projectSeries->id,
                'title' => $record->projectSeries->title,
                'date' => $this->date($record->projectSeries->date),
                'endDate' => $this->date($record->projectSeries->end_date),
            ] : null,
            'work' => $record->work ? [
                'id' => $record->work->id,
                'title' => $record->work->title,
                'partNumber' => $record->part_number,
                'partLabel' => $record->part_label,
                'totalParts' => $record->work->total_parts,
            ] : null,
            'agency' => $record->agency ? ['name' => $record->agency->name, 'acronym' => $record->agency->acronym] : null,
            'productionUnit' => $record->division ? ['name' => $record->division->name, 'acronym' => $record->division->acronym] : null,
            'isApproved' => $record->review_status === ReviewStatus::Approved,
            'reviewStatus' => $record->review_status->value,
            'publishedAt' => $this->timestamp($record->published_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function descriptive(Record $record): array
    {
        $hasLocation = $record->location !== null || $record->region_id !== null || $record->latitude !== null;

        return [
            'description' => $record->description,
            'genre' => $record->genre?->name,
            'videoType' => $record->videoType?->name,
            'language' => $record->language ? ['code' => $record->language, 'confidence' => $record->language_confidence] : null,
            'countryOfOrigin' => $record->country_origin,
            'dateRecorded' => $record->date_recorded ? [
                'value' => $this->date($record->date_recorded),
                'precision' => $record->date_recorded_precision?->value,
                'source' => $record->date_recorded_source?->value,
            ] : null,
            'datePublished' => $this->date($record->date_published),
            'season' => $record->season_number,
            'episode' => $record->episode_number,
            'location' => $hasLocation ? [
                'text' => $record->location,
                'region' => $record->region?->name,
                'province' => $record->province?->name,
                'city' => $record->city?->name,
                'barangay' => $record->barangay?->name,
                'latitude' => $record->latitude,
                'longitude' => $record->longitude,
                'source' => $record->location_source?->value,
            ] : null,
            'subjects' => $record->subjects->map(fn (RecordSubject $subject): array => [
                'subject' => $subject->subject,
                'type' => $subject->type->value,
                'source' => $subject->source->value,
                'confirmed' => $subject->is_confirmed,
            ])->values()->all(),
            'keywords' => $record->keywords->map(fn (RecordKeyword $keyword): array => [
                'keyword' => $keyword->keyword,
                'source' => $keyword->source->value,
                'confirmed' => $keyword->is_confirmed,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function technicalFile(RecordFile $file): array
    {
        $technical = $file->technicalMetadata;

        return [
            'id' => $file->id,
            'role' => $file->role->value,
            'originalFilename' => $file->original_filename,
            'mimeType' => $file->mime_type,
            'fileSize' => $file->file_size,
            'checksums' => [
                'md5' => $file->md5_checksum,
                'sha256' => $file->sha256_checksum,
                'status' => $file->checksum_status->value,
            ],
            'fileFormat' => $technical?->file_format,
            'duration' => $technical?->duration,
            'bitrate' => $technical?->bitrate,
            'startTimecode' => $technical?->start_timecode,
            'video' => $technical?->video_codec === null ? null : [
                'codec' => $technical->video_codec,
                'profile' => $technical->video_profile,
                'resolution' => $technical->resolution,
                'frameRate' => $technical->frame_rate,
                'aspectRatio' => $technical->aspect_ratio,
                'scanType' => $technical->scan_type?->value,
                'bitDepth' => $technical->bit_depth,
                'chromaSubsampling' => $technical->chroma_subsampling,
                'colorSpace' => $technical->color_space,
                'colorPrimaries' => $technical->color_primaries,
                'colorTransfer' => $technical->color_transfer,
            ],
            'audio' => $technical?->audio_codec === null ? null : [
                'codec' => $technical->audio_codec,
                'channels' => $technical->audio_channels,
                'sampleRate' => $technical->audio_sample_rate,
                'tracks' => $technical->audio_tracks ?? [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rights(Record $record): ?array
    {
        $rights = $record->rights;

        return $rights === null ? null : [
            'copyrightHolder' => $rights->copyright_holder,
            'copyrightYear' => $rights->copyright_year,
            'rightsStatement' => $rights->rights_statement,
            'license' => $rights->license,
            'licenseUrl' => $rights->license_url,
            'accessLevel' => $rights->access_level->value,
            'usageRestrictions' => $rights->usage_restrictions,
            'territories' => $rights->territories ?? [],
            'embargoUntil' => $this->date($rights->embargo_until),
            'permissionStatus' => $rights->permission_status->value,
            'releaseConsent' => $rights->release_consent->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function provenance(Record $record): array
    {
        $provenance = $record->provenance;

        return [
            'ingestedAt' => $this->timestamp($record->created_at),
            'sourceCarrier' => $provenance?->source_carrier === null ? null : [
                'type' => $provenance->source_carrier->value,
                'label' => $provenance->carrier_label,
                'id' => $provenance->carrier_id,
            ],
            'transfer' => $provenance === null ? null : [
                'date' => $this->date($provenance->transfer_date),
                'facility' => $provenance->transfer_facility,
                'operator' => $provenance->transfer_operator,
                'deck' => $provenance->transfer_deck,
                'settings' => $provenance->transfer_settings,
            ],
            'auditLog' => $record->auditLogs->map(fn (VideoAuditLog $log): array => [
                'action' => $log->action->value,
                'fileId' => $log->record_file_id,
                'performedBy' => $log->performer->name ?? ($log->performed_by === null ? 'System' : null),
                'performedAt' => $this->timestamp($log->performed_at),
                'remarks' => $log->remarks,
            ])->values()->all(),
            'fixityChecks' => $record->files->flatMap(fn (RecordFile $file) => $file->fixityChecks->map(fn (FixityCheck $check): array => [
                'fileId' => $file->id,
                'algorithm' => $check->algorithm,
                'matched' => $check->matched,
                'checkedAt' => $this->timestamp($check->checked_at),
            ]))->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function segments(RecordTranscript $transcript, bool $withSpeakers): array
    {
        return array_values($transcript->segments->map(fn (RecordSegment $segment): array => [
            'id' => $segment->position,
            'start' => $segment->start,
            'end' => $segment->end,
            ...($withSpeakers ? ['speaker' => $segment->speaker?->label] : []),
            'text' => $segment->text,
            ...($withSpeakers ? ['edited' => $segment->is_edited] : []),
        ])->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ai(Record $record): ?array
    {
        $metadata = $record->activeMetadata;

        if ($metadata === null) {
            return null;
        }

        $payload = $metadata->payload;

        return [
            'preset' => $metadata->preset->value,
            'model' => $metadata->model,
            'customPrompt' => $metadata->custom_prompt,
            'titles' => $payload['titles'] ?? [],
            'selectedTitle' => $payload['selectedTitle'] ?? null,
            'description' => $payload['description'] ?? null,
            'summary' => $payload['summary'] ?? null,
            'tags' => $payload['tags'] ?? [],
            'keywords' => $payload['keywords'] ?? [],
            'hashtags' => $payload['hashtags'] ?? [],
            'chapters' => $payload['chapters'] ?? [],
            'quotes' => $payload['quotes'] ?? [],
            'tone' => $payload['tone'] ?? null,
            'contentFlags' => $payload['contentFlags'] ?? null,
        ];
    }

    private function date(?DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d');
    }

    private function timestamp(?DateTimeInterface $value): ?string
    {
        return $value === null ? null : CarbonImmutable::instance($value)->utc()->toIso8601ZuluString();
    }
}
