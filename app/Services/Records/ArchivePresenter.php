<?php

namespace App\Services\Records;

use App\Enums\AccessLevel;
use App\Enums\ArchivalValue;
use App\Enums\AttachmentKind;
use App\Enums\DatePrecision;
use App\Enums\DispositionAction;
use App\Enums\FileRole;
use App\Enums\PermissionStatus;
use App\Enums\ReleaseConsent;
use App\Enums\SceneKind;
use App\Enums\SecurityClassification;
use App\Enums\SourceCarrier;
use App\Enums\SubjectType;
use App\Models\FixityCheck;
use App\Models\Genre;
use App\Models\Record;
use App\Models\RecordAttachment;
use App\Models\RecordFile;
use App\Models\RecordKeyword;
use App\Models\RecordScene;
use App\Models\RecordSubject;
use App\Models\RecordTechnicalMetadata;
use App\Models\User;
use App\Models\VideoAuditLog;
use App\Models\VideoType;
use App\Models\Work;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The workspace's Archive panel data (PROJECT_SPEC §9, ArchivePanel): the
 * catalogue fields by sub-tab, subjects and keywords, rights, provenance,
 * files with their technical metadata, fixity history, the audit log, and
 * suggestions from container tags. Only the fields the panel shows are sent.
 */
class ArchivePresenter
{
    private const int AUDIT_LIMIT = 100;

    private const int FIXITY_LIMIT = 50;

    /**
     * @return array<string, mixed>
     */
    public function present(Record $record): array
    {
        $record->loadMissing([
            'rights',
            'provenance',
            'subjects' => fn ($query) => $query->orderBy('is_confirmed')->orderBy('subject'),
            'keywords' => fn ($query) => $query->orderBy('is_confirmed')->orderBy('keyword'),
            'files' => fn ($query) => $query->orderByRaw("case role when 'master' then 0 when 'mezzanine' then 1 when 'proxy' then 2 else 3 end"),
            'files.technicalMetadata',
            'attachments' => fn ($query) => $query->latest(),
            'work:id,title,total_parts,created_by',
            'transcripts:id,record_id,language,is_original,status',
        ]);

        return [
            'descriptive' => [
                'title' => $record->title,
                'alternative_title' => $record->alternative_title,
                'description' => $record->description,
                'genre_id' => $record->genre_id,
                'video_type_id' => $record->video_type_id,
                'language' => $record->language,
                'country_origin' => $record->country_origin,
                'date_recorded' => $this->date($record->date_recorded),
                'date_recorded_precision' => $record->date_recorded_precision?->value,
                'date_recorded_source' => $record->date_recorded_source?->value,
                'date_published' => $this->date($record->date_published),
                'season_number' => $record->season_number,
                'episode_number' => $record->episode_number,
                'project_id' => $record->project_id,
                'project_series_id' => $record->project_series_id,
            ],
            'location' => [
                'location' => $record->location,
                'region_id' => $record->region_id,
                'prov_id' => $record->prov_id,
                'city_id' => $record->city_id,
                'brgy_id' => $record->brgy_id,
                'latitude' => $record->latitude === null ? null : (float) $record->latitude,
                'longitude' => $record->longitude === null ? null : (float) $record->longitude,
                'location_source' => $record->location_source?->value,
            ],
            'credits' => [
                'agency_id' => $record->agency_id,
                'division_id' => $record->division_id,
            ],
            'archival' => [
                'accession_number' => $record->accession_number,
                'security_classification' => $record->security_classification->value,
                'retention_period' => $record->retention_period,
                'archival_value' => $record->archival_value?->value,
                'disposition_date' => $this->date($record->disposition_date),
                'disposition_action' => $record->disposition_action?->value,
            ],
            'subjects' => $record->subjects->map(fn (RecordSubject $subject): array => [
                'id' => $subject->id,
                'subject' => $subject->subject,
                'type' => $subject->type->value,
                'source' => $subject->source->value,
                'is_confirmed' => $subject->is_confirmed,
            ])->values()->all(),
            'keywords' => $record->keywords->map(fn (RecordKeyword $keyword): array => [
                'id' => $keyword->id,
                'keyword' => $keyword->keyword,
                'source' => $keyword->source->value,
                'is_confirmed' => $keyword->is_confirmed,
            ])->values()->all(),
            'rights' => $record->rights === null ? null : [
                'copyright_holder' => $record->rights->copyright_holder,
                'copyright_year' => $record->rights->copyright_year,
                'rights_statement' => $record->rights->rights_statement,
                'license' => $record->rights->license,
                'license_url' => $record->rights->license_url,
                'access_level' => $record->rights->access_level->value,
                'usage_restrictions' => $record->rights->usage_restrictions,
                'territories' => $record->rights->territories ?? [],
                'embargo_until' => $this->date($record->rights->embargo_until),
                'permission_status' => $record->rights->permission_status->value,
                'release_consent' => $record->rights->release_consent->value,
            ],
            'provenance' => $record->provenance === null ? null : [
                'source_carrier' => $record->provenance->source_carrier?->value,
                'carrier_label' => $record->provenance->carrier_label,
                'carrier_id' => $record->provenance->carrier_id,
                'transfer_date' => $this->date($record->provenance->transfer_date),
                'transfer_facility' => $record->provenance->transfer_facility,
                'transfer_operator' => $record->provenance->transfer_operator,
                'transfer_deck' => $record->provenance->transfer_deck,
                'transfer_settings' => $record->provenance->transfer_settings,
                'notes' => $record->provenance->notes,
            ],
            'files' => $record->files->map(fn (RecordFile $file): array => $this->file($file))->values()->all(),
            'attachments' => $record->attachments->map(fn (RecordAttachment $attachment): array => self::attachment($attachment))->values()->all(),
            'work' => $record->work === null ? null : [
                'id' => $record->work->id,
                'title' => $record->work->title,
                'total_parts' => $record->work->total_parts,
                'parts' => $record->work->records()
                    ->orderBy('part_number')
                    ->get(['id', 'title', 'part_number', 'part_label', 'work_id'])
                    ->map(fn (Record $part): array => $part->only(['id', 'title', 'part_number', 'part_label']))
                    ->all(),
            ],
            'part_number' => $record->part_number,
            'part_label' => $record->part_label,
            'transcripts' => $record->transcripts->map(fn ($transcript): array => [
                'language' => $transcript->language,
                'is_original' => $transcript->is_original,
                'status' => $transcript->status->value,
            ])->values()->all(),
            // Shot/scene segments (Phase 5): the shots themselves are in the Visuals tab.
            'segments' => [
                'shot_count' => $record->scenes()->where('kind', SceneKind::Shot)->count(),
                'scenes' => array_values($record->scenes()
                    ->where('kind', SceneKind::Scene)
                    ->get(['id', 'record_id', 'start', 'end', 'tc_in', 'tc_out', 'label', 'description'])
                    ->map(fn (RecordScene $scene): array => VisualsPresenter::scene($scene))
                    ->all()),
            ],
            'fixity' => $this->fixity($record),
            'audit' => $this->audit($record),
            'suggestions' => $this->containerSuggestions($record),
            'embargoed' => $record->rights?->isEmbargoed() ?? false,
        ];
    }

    /**
     * Select options for the Archive panel.
     *
     * @return array<string, mixed>
     */
    public function options(User $user): array
    {
        return [
            'genres' => Genre::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->map(fn (Genre $genre): array => $genre->only(['id', 'name']))->all(),
            'videoTypes' => VideoType::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->map(fn (VideoType $type): array => $type->only(['id', 'name']))->all(),
            'datePrecisions' => DatePrecision::options(),
            'subjectTypes' => SubjectType::options(),
            'classifications' => SecurityClassification::options(),
            'archivalValues' => ArchivalValue::options(),
            'dispositionActions' => DispositionAction::options(),
            'accessLevels' => AccessLevel::options(),
            'permissionStatuses' => PermissionStatus::options(),
            'releaseConsents' => ReleaseConsent::options(),
            'sourceCarriers' => SourceCarrier::options(),
            'attachmentKinds' => AttachmentKind::options(),
            'works' => Work::query()
                ->when(! $user->isAdmin(), fn ($query) => $query->where('created_by', $user->id))
                ->latest()
                ->limit(100)
                ->get(['id', 'title', 'total_parts'])
                ->map(fn (Work $work): array => $work->only(['id', 'title', 'total_parts']))
                ->all(),
            'maxAttachmentMb' => (int) config('archive.max_attachment_mb'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function attachment(RecordAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'kind' => $attachment->kind->value,
            'original_filename' => $attachment->original_filename,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'sha256_checksum' => $attachment->sha256_checksum,
            'description' => $attachment->description,
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function file(RecordFile $file): array
    {
        $technical = $file->technicalMetadata;

        return [
            'id' => $file->id,
            'role' => $file->role->value,
            'original_filename' => $file->original_filename,
            'mime_type' => $file->mime_type,
            'file_size' => $file->file_size,
            'md5_checksum' => $file->md5_checksum,
            'sha256_checksum' => $file->sha256_checksum,
            'checksum_status' => $file->checksum_status->value,
            'created_at' => $file->created_at?->toIso8601String(),
            'technical' => $technical === null ? null : $this->technical($technical),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function technical(RecordTechnicalMetadata $technical): array
    {
        return [
            'file_format' => $technical->file_format,
            'duration' => $technical->duration,
            'bitrate' => $technical->bitrate,
            'start_timecode' => $technical->start_timecode,
            'video_codec' => $technical->video_codec,
            'video_profile' => $technical->video_profile,
            'resolution' => $technical->resolution,
            'frame_rate' => $technical->frame_rate,
            'aspect_ratio' => $technical->aspect_ratio,
            'scan_type' => $technical->scan_type?->value,
            'bit_depth' => $technical->bit_depth,
            'chroma_subsampling' => $technical->chroma_subsampling,
            'color_space' => $technical->color_space,
            'color_primaries' => $technical->color_primaries,
            'color_transfer' => $technical->color_transfer,
            'audio_codec' => $technical->audio_codec,
            'audio_channels' => $technical->audio_channels,
            'audio_sample_rate' => $technical->audio_sample_rate,
            'audio_tracks' => $technical->audio_tracks ?? [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fixity(Record $record): array
    {
        $roles = $record->files->mapWithKeys(fn (RecordFile $file): array => [$file->id => $file->role->value]);

        return array_values(FixityCheck::query()
            ->whereIn('record_file_id', $roles->keys())
            ->latest('checked_at')
            ->limit(self::FIXITY_LIMIT)
            ->get(['id', 'record_file_id', 'algorithm', 'matched', 'triggered_by', 'checked_at'])
            ->map(fn (FixityCheck $check): array => [
                'id' => $check->id,
                'file_id' => $check->record_file_id,
                'role' => $roles->get($check->record_file_id),
                'algorithm' => $check->algorithm,
                'matched' => $check->matched,
                'triggered_by' => $check->triggered_by->value,
                'checked_at' => $check->checked_at->toIso8601String(),
            ])
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function audit(Record $record): array
    {
        return array_values($record->auditLogs()
            ->with('performer:id,name')
            ->latest('performed_at')
            ->limit(self::AUDIT_LIMIT)
            ->get(['id', 'record_id', 'record_file_id', 'action', 'performed_by', 'performed_at', 'remarks'])
            ->map(fn (VideoAuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action->value,
                'file_id' => $log->record_file_id,
                'performed_by' => $log->performer->name ?? ($log->performed_by === null ? 'System' : 'Deleted user'),
                'performed_at' => $log->performed_at->toIso8601String(),
                'remarks' => $log->remarks,
            ])
            ->all());
    }

    /**
     * Suggestions read from the master's container tags at probe time: the
     * creation time (often the copy or encode date, so it's only a hint) and
     * GPS coordinates (ISO 6709, e.g. "+10.3157+123.8854/").
     *
     * @return array{date_recorded: string|null, latitude: float|null, longitude: float|null}
     */
    private function containerSuggestions(Record $record): array
    {
        $master = $record->files->first(fn (RecordFile $file): bool => $file->role === FileRole::Master);
        /** @var array<string, mixed> $tags */
        $tags = $master?->technicalMetadata?->raw_probe['container_tags'] ?? [];
        $date = null;

        if (is_string($tags['creation_time'] ?? null)) {
            $parsed = rescue(fn () => CarbonImmutable::parse($tags['creation_time']), report: false);
            // Cameras without a set clock write 1970 or 1904; those aren't hints.
            $date = $parsed !== null && $parsed->year >= 1980 && $parsed->isPast() ? $parsed->toDateString() : null;
        }

        $latitude = $longitude = null;

        if (is_string($tags['location'] ?? null) && preg_match('/^([+-]\d+(?:\.\d+)?)([+-]\d+(?:\.\d+)?)/', $tags['location'], $match) === 1) {
            [$lat, $lng] = [(float) $match[1], (float) $match[2]];

            if (abs($lat) <= 90 && abs($lng) <= 180 && ($lat !== 0.0 || $lng !== 0.0)) {
                [$latitude, $longitude] = [$lat, $lng];
            }
        }

        return ['date_recorded' => $date, 'latitude' => $latitude, 'longitude' => $longitude];
    }

    private function date(?DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d');
    }
}
