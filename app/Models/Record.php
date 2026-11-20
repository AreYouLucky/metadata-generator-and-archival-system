<?php

namespace App\Models;

use App\Enums\ArchivalValue;
use App\Enums\ProcessingStatus;
use App\Enums\SecurityClassification;
use Database\Factories\RecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One archived video. Workflow fields (status, approval, stored file) are set explicitly, never mass-assigned.
 *
 * @property string $id
 * @property int $created_by
 * @property string|null $project_id
 * @property string|null $project_series_id
 * @property int|null $agency_id
 * @property int|null $division_id
 * @property string $title
 * @property string|null $alternative_title
 * @property string|null $description
 * @property string|null $genre
 * @property string|null $video_type
 * @property string|null $language
 * @property string|null $country_origin
 * @property Carbon|null $date_recorded
 * @property Carbon|null $date_published
 * @property string|null $location
 * @property int|null $region_id
 * @property int|null $prov_id
 * @property int|null $city_id
 * @property int|null $brgy_id
 * @property string|null $storage_location
 * @property string|null $filename
 * @property string|null $thumbnail_path
 * @property SecurityClassification $security_classification
 * @property string|null $retention_period
 * @property ArchivalValue|null $archival_value
 * @property ProcessingStatus $processing_status
 * @property string|null $processing_error
 * @property bool $is_approved
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['project_id', 'project_series_id', 'agency_id', 'division_id', 'title', 'alternative_title', 'description', 'genre', 'video_type', 'language', 'country_origin', 'date_recorded', 'date_published', 'location', 'region_id', 'prov_id', 'city_id', 'brgy_id', 'security_classification', 'retention_period', 'archival_value'])]
#[Hidden(['storage_location', 'filename'])]
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
        'is_approved' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_recorded' => 'date',
            'date_published' => 'date',
            'security_classification' => SecurityClassification::class,
            'archival_value' => ArchivalValue::class,
            'processing_status' => ProcessingStatus::class,
            'is_approved' => 'boolean',
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
     * @return HasOne<RecordTechnicalMetadata, $this>
     */
    public function technicalMetadata(): HasOne
    {
        return $this->hasOne(RecordTechnicalMetadata::class);
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
}
