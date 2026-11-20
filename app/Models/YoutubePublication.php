<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Enums\YoutubePrivacy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A publish attempt of a project to YouTube.
 *
 * @property int $id
 * @property string $project_id
 * @property int|null $youtube_account_id
 * @property string|null $youtube_video_id
 * @property YoutubePrivacy $privacy
 * @property TaskStatus $status
 * @property string|null $error
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['youtube_account_id', 'privacy'])]
class YoutubePublication extends Model
{
    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'privacy' => 'private',
        'status' => 'queued',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'privacy' => YoutubePrivacy::class,
            'status' => TaskStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<YoutubeAccount, $this>
     */
    public function youtubeAccount(): BelongsTo
    {
        return $this->belongsTo(YoutubeAccount::class);
    }
}
