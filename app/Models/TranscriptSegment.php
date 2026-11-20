<?php

namespace App\Models;

use Database\Factories\TranscriptSegmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One timed line of a project's transcript.
 *
 * @property int $id
 * @property string $project_id
 * @property int $position
 * @property float $start
 * @property float $end
 * @property string $text
 * @property string $original_text
 * @property int|null $speaker_id
 * @property bool $is_edited
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['position', 'start', 'end', 'text', 'original_text', 'speaker_id', 'is_edited'])]
class TranscriptSegment extends Model
{
    /** @use HasFactory<TranscriptSegmentFactory> */
    use HasFactory;

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_edited' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'start' => 'float',
            'end' => 'float',
            'is_edited' => 'boolean',
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
     * @return BelongsTo<Speaker, $this>
     */
    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Speaker::class);
    }
}
