<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Enums\YouTubePrivacy;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\YoutubePublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to publish a record to YouTube (Phase 7): what was sent and how
 * it went. Only the encoder's choices are fillable; the channel, status,
 * upload session and YouTube's answers are set by the system.
 *
 * @property int $id
 * @property string $record_id
 * @property int|null $user_id
 * @property int|null $youtube_account_id
 * @property string $channel_id
 * @property string $channel_title
 * @property PublicationStatus $status
 * @property YouTubePrivacy $privacy_status
 * @property string $title
 * @property string $description
 * @property list<string> $tags
 * @property string $category_id
 * @property bool $made_for_kids
 * @property bool $with_thumbnail
 * @property string|null $upload_session_url
 * @property int|null $file_size
 * @property int $bytes_uploaded
 * @property string|null $video_id
 * @property string|null $youtube_upload_status
 * @property string|null $youtube_processing_status
 * @property string|null $thumbnail_error
 * @property string|null $error
 * @property Carbon|null $published_at
 * @property Carbon|null $status_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['privacy_status', 'title', 'description', 'tags', 'category_id', 'made_for_kids', 'with_thumbnail'])]
#[Hidden(['upload_session_url'])]
class YoutubePublication extends Model
{
    /** @use HasFactory<YoutubePublicationFactory> */
    use HasFactory;

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'bytes_uploaded' => 0,
        'made_for_kids' => false,
        'with_thumbnail' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublicationStatus::class,
            'privacy_status' => YouTubePrivacy::class,
            'tags' => 'array',
            'made_for_kids' => 'boolean',
            'with_thumbnail' => 'boolean',
            // A resumable session URL accepts bytes without further sign-in for a week.
            'upload_session_url' => 'encrypted',
            'file_size' => 'integer',
            'bytes_uploaded' => 'integer',
            'published_at' => 'datetime',
            'status_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<YoutubeAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(YoutubeAccount::class, 'youtube_account_id');
    }

    public function videoUrl(): ?string
    {
        return $this->video_id === null ? null : 'https://youtu.be/'.rawurlencode($this->video_id);
    }

    /**
     * 0–100 while uploading; 100 once the file is on YouTube.
     */
    public function uploadPercent(): int
    {
        if ($this->video_id !== null) {
            return 100;
        }

        return $this->file_size ? (int) min(99, floor($this->bytes_uploaded / $this->file_size * 100)) : 0;
    }
}
