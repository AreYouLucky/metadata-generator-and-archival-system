<?php

namespace App\Models;

use App\Enums\ChecksumStatus;
use App\Enums\MediaFileRole;
use App\Enums\ProjectStatus;
use App\Enums\ReviewStatus;
use App\Enums\SourceType;
use Database\Factories\ProjectFactory;
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
 * A video being processed and catalogued. Workflow fields are set explicitly, never mass-assigned.
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $batch_id
 * @property string|null $work_id
 * @property int|null $part_number
 * @property string|null $part_label
 * @property int|null $category_id
 * @property int|null $catalogue_event_id
 * @property string|null $accession_number
 * @property string $title
 * @property ProjectStatus $status
 * @property ReviewStatus $review_status
 * @property Carbon|null $submitted_at
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $published_at
 * @property SourceType $source_type
 * @property string|null $source_url
 * @property string|null $original_filename
 * @property string|null $storage_path
 * @property string|null $audio_path
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property float|null $duration_sec
 * @property int|null $width
 * @property int|null $height
 * @property float|null $fps
 * @property string|null $video_codec
 * @property string|null $audio_codec
 * @property int|null $bitrate
 * @property string|null $language
 * @property float|null $language_confidence
 * @property array<string, mixed>|null $options
 * @property string|null $selected_title
 * @property int|null $thumbnail_frame_id
 * @property ChecksumStatus $checksum_status
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['work_id', 'part_number', 'part_label', 'category_id', 'catalogue_event_id', 'title', 'source_type', 'source_url', 'original_filename', 'options', 'selected_title'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'review_status' => 'draft',
        'checksum_status' => 'pending',
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
            'status' => ProjectStatus::class,
            'review_status' => ReviewStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'source_type' => SourceType::class,
            'size_bytes' => 'integer',
            'duration_sec' => 'float',
            'width' => 'integer',
            'height' => 'integer',
            'fps' => 'float',
            'bitrate' => 'integer',
            'language_confidence' => 'float',
            'options' => 'array',
            'checksum_status' => ChecksumStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<Work, $this>
     */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<CatalogueEvent, $this>
     */
    public function catalogueEvent(): BelongsTo
    {
        return $this->belongsTo(CatalogueEvent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<Frame, $this>
     */
    public function thumbnailFrame(): BelongsTo
    {
        return $this->belongsTo(Frame::class, 'thumbnail_frame_id');
    }

    /**
     * @return HasMany<ProjectStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class);
    }

    /**
     * @return HasMany<Speaker, $this>
     */
    public function speakers(): HasMany
    {
        return $this->hasMany(Speaker::class);
    }

    /**
     * @return HasMany<TranscriptSegment, $this>
     */
    public function transcriptSegments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class)->orderBy('position');
    }

    /**
     * @return HasMany<Translation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    /**
     * @return HasMany<MetadataGeneration, $this>
     */
    public function metadataGenerations(): HasMany
    {
        return $this->hasMany(MetadataGeneration::class);
    }

    /**
     * @return HasOne<MetadataGeneration, $this>
     */
    public function activeMetadata(): HasOne
    {
        return $this->hasOne(MetadataGeneration::class)->where('is_active', true);
    }

    /**
     * @return HasMany<Scene, $this>
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class)->orderBy('start');
    }

    /**
     * @return HasMany<Frame, $this>
     */
    public function frames(): HasMany
    {
        return $this->hasMany(Frame::class)->orderBy('time_sec');
    }

    /**
     * @return HasMany<TranscriptChunk, $this>
     */
    public function transcriptChunks(): HasMany
    {
        return $this->hasMany(TranscriptChunk::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * @return HasMany<Clip, $this>
     */
    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class);
    }

    /**
     * @return HasMany<YoutubePublication, $this>
     */
    public function youtubePublications(): HasMany
    {
        return $this->hasMany(YoutubePublication::class);
    }

    /**
     * @return HasMany<MediaFile, $this>
     */
    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    /**
     * @return HasOne<MediaFile, $this>
     */
    public function masterFile(): HasOne
    {
        return $this->hasOne(MediaFile::class)->where('role', MediaFileRole::Master);
    }

    /**
     * @return HasOne<CatalogueRecord, $this>
     */
    public function catalogueRecord(): HasOne
    {
        return $this->hasOne(CatalogueRecord::class);
    }

    /**
     * @return HasMany<Subject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    /**
     * @return HasOne<RightsRecord, $this>
     */
    public function rightsRecord(): HasOne
    {
        return $this->hasOne(RightsRecord::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * @return HasOne<ProvenanceRecord, $this>
     */
    public function provenanceRecord(): HasOne
    {
        return $this->hasOne(ProvenanceRecord::class);
    }

    /**
     * @return HasMany<PreservationEvent, $this>
     */
    public function preservationEvents(): HasMany
    {
        return $this->hasMany(PreservationEvent::class)->orderBy('occurred_at');
    }

    /**
     * @return HasMany<ProjectReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProjectReview::class)->orderBy('created_at');
    }

    /**
     * @return HasMany<UsageRecord, $this>
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }
}
