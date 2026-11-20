<?php

namespace App\Models;

use App\Enums\AgentType;
use App\Enums\EventOutcome;
use App\Enums\PreservationEventType;
use App\Models\Concerns\AppendOnly;
use Database\Factories\PreservationEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An entry in the append-only preservation log. Rows are never updated or deleted.
 *
 * @property int $id
 * @property string|null $project_id
 * @property string|null $media_file_id
 * @property PreservationEventType $type
 * @property EventOutcome $outcome
 * @property string|null $detail
 * @property AgentType $agent_type
 * @property string|null $agent
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 */
#[Fillable(['project_id', 'media_file_id', 'type', 'outcome', 'detail', 'agent_type', 'agent', 'occurred_at'])]
class PreservationEvent extends Model
{
    /** @use HasFactory<PreservationEventFactory> */
    use AppendOnly, HasFactory;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PreservationEventType::class,
            'outcome' => EventOutcome::class,
            'agent_type' => AgentType::class,
            'occurred_at' => 'datetime',
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
     * @return BelongsTo<MediaFile, $this>
     */
    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }
}
