<?php

namespace App\Models;

use App\Enums\ArchivalValue;
use App\Enums\DatePrecision;
use App\Enums\DispositionAction;
use App\Enums\FileRole;
use App\Enums\MetadataSource;
use App\Enums\ProcessingStatus;
use App\Enums\ReviewStatus;
use App\Enums\SecurityClassification;
use Database\Factories\RecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One archived video. Workflow fields (accession number, processing, approval) are set explicitly, never mass-assigned.
 *
 * @property string $id
 * @property int $created_by
 * @property string|null $accession_number
 * @property string|null $project_id
 * @property string|null $project_series_id
 * @property string|null $work_id
 * @property int|null $part_number
 * @property string|null $part_label
 * @property int|null $agency_id
 * @property int|null $division_id
 * @property string $title
 * @property string|null $alternative_title
 * @property string|null $description
 * @property int|null $genre_id
 * @property int|null $video_type_id
 * @property string|null $language
 * @property float|null $language_confidence
 * @property string|null $country_origin
 * @property Carbon|null $date_recorded
 * @property DatePrecision|null $date_recorded_precision
 * @property MetadataSource|null $date_recorded_source
 * @property Carbon|null $date_published
 * @property int|null $season_number
 * @property int|null $episode_number
 * @property string|null $location
 * @property int|null $region_id
 * @property int|null $prov_id
 * @property int|null $city_id
 * @property int|null $brgy_id
 * @property float|null $latitude
 * @property float|null $longitude
 * @property MetadataSource|null $location_source
 * @property string|null $thumbnail_path
 * @property int|null $thumbnail_frame_id
 * @property SecurityClassification $security_classification
 * @property string|null $retention_period
 * @property ArchivalValue|null $archival_value
 * @property Carbon|null $disposition_date
 * @property DispositionAction|null $disposition_action
 * @property ProcessingStatus $processing_status
 * @property string|null $processing_error
 * @property ReviewStatus $review_status
 * @property Carbon|null $submitted_at
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['project_id', 'project_series_id', 'work_id', 'part_number', 'part_label', 'agency_id', 'division_id', 'title', 'alternative_title', 'description', 'genre_id', 'video_type_id', 'language', 'country_origin', 'date_recorded', 'date_recorded_precision', 'date_recorded_source', 'date_published', 'season_number', 'episode_number', 'location', 'region_id', 'prov_id', 'city_id', 'brgy_id', 'latitude', 'longitude', 'location_source', 'security_classification', 'retention_period', 'archival_value', 'disposition_date', 'disposition_action'])]
class Record extends Model
{
    /** @use HasFactory<RecordFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'security_classification' => 'unclassified',
        'processing_status' => 'queued',
        'review_status' => 'draft',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'part_number' => 'integer',
            'language_confidence' => 'float',
            'date_recorded' => 'date',
            'date_recorded_precision' => DatePrecision::class,
            'date_recorded_source' => MetadataSource::class,
            'date_published' => 'date',
            'season_number' => 'integer',
            'episode_number' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'location_source' => MetadataSource::class,
            'security_classification' => SecurityClassification::class,
            'archival_value' => ArchivalValue::class,
            'disposition_date' => 'date',
            'disposition_action' => DispositionAction::class,
            'processing_status' => ProcessingStatus::class,
            'review_status' => ReviewStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ProjectSeries, $this>
     */
    public function projectSeries(): BelongsTo
    {
        return $this->belongsTo(ProjectSeries::class, 'project_series_id');
    }

    /**
     * @return BelongsTo<Work, $this>
     */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * @return BelongsTo<ProductionAgency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(ProductionAgency::class, 'agency_id');
    }

    /**
     * @return BelongsTo<ProductionAgency, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(ProductionAgency::class, 'division_id');
    }

    /**
     * @return BelongsTo<Genre, $this>
     */
    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    /**
     * @return BelongsTo<VideoType, $this>
     */
    public function videoType(): BelongsTo
    {
        return $this->belongsTo(VideoType::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'prov_id');
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * @return BelongsTo<Barangay, $this>
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'brgy_id');
    }

    /**
     * @return BelongsTo<RecordFrame, $this>
     */
    public function thumbnailFrame(): BelongsTo
    {
        return $this->belongsTo(RecordFrame::class, 'thumbnail_frame_id');
    }

    /**
     * @return HasMany<RecordFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(RecordFile::class);
    }

    /**
     * @return HasOne<RecordFile, $this>
     */
    public function masterFile(): HasOne
    {
        return $this->hasOne(RecordFile::class)->where('role', FileRole::Master);
    }

    /**
     * @return HasOne<RecordFile, $this>
     */
    public function proxyFile(): HasOne
    {
        return $this->hasOne(RecordFile::class)->where('role', FileRole::Proxy);
    }

    /**
     * @return HasMany<RecordStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(RecordStage::class);
    }

    /**
     * @return HasMany<RecordMetadata, $this>
     */
    public function metadata(): HasMany
    {
        return $this->hasMany(RecordMetadata::class);
    }

    /**
     * @return HasOne<RecordMetadata, $this>
     */
    public function activeMetadata(): HasOne
    {
        return $this->hasOne(RecordMetadata::class)->where('is_active', true);
    }

    /**
     * @return HasMany<RecordContributor, $this>
     */
    public function contributors(): HasMany
    {
        return $this->hasMany(RecordContributor::class)->orderBy('sort_order');
    }

    /**
     * @return HasOne<RecordRights, $this>
     */
    public function rights(): HasOne
    {
        return $this->hasOne(RecordRights::class);
    }

    /**
     * @return HasMany<RecordSubject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(RecordSubject::class);
    }

    /**
     * @return HasMany<RecordKeyword, $this>
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(RecordKeyword::class);
    }

    /**
     * @return HasMany<RecordSpeaker, $this>
     */
    public function speakers(): HasMany
    {
        return $this->hasMany(RecordSpeaker::class);
    }

    /**
     * @return HasMany<RecordTranscript, $this>
     */
    public function transcripts(): HasMany
    {
        return $this->hasMany(RecordTranscript::class);
    }

    /**
     * @return HasOne<RecordTranscript, $this>
     */
    public function originalTranscript(): HasOne
    {
        return $this->hasOne(RecordTranscript::class)->where('is_original', true);
    }

    /**
     * @return HasMany<RecordFrame, $this>
     */
    public function frames(): HasMany
    {
        return $this->hasMany(RecordFrame::class)->orderBy('time_sec');
    }

    /**
     * @return HasMany<RecordScene, $this>
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(RecordScene::class)->orderBy('start');
    }

    /**
     * @return HasOne<RecordProvenance, $this>
     */
    public function provenance(): HasOne
    {
        return $this->hasOne(RecordProvenance::class);
    }

    /**
     * @return HasMany<RecordAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(RecordAttachment::class);
    }

    /**
     * @return HasMany<VideoAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(VideoAuditLog::class)->orderBy('performed_at');
    }

    /**
     * @return HasMany<AccessLog, $this>
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    /**
     * @return HasMany<UsageRecord, $this>
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }
}
