<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\RecordTranscriptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A record's transcript in one language: the original or a translation.
 *
 * @property int $id
 * @property string $record_id
 * @property string $language
 * @property bool $is_original
 * @property TaskStatus $status
 * @property int $progress
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['language', 'is_original'])]
class RecordTranscript extends Model
{
    /** @use HasFactory<RecordTranscriptFactory> */
    use HasFactory;

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_original' => true,
        'status' => 'queued',
        'progress' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_original' => 'boolean',
            'status' => TaskStatus::class,
            'progress' => 'integer',
        ];
    }

    /**
     * Whether a translation is still queued or running (a new request must wait).
     */
    public function isBusy(): bool
    {
        return in_array($this->status, [TaskStatus::Queued, TaskStatus::Processing], true);
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /**
     * @return HasMany<RecordSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(RecordSegment::class)->orderBy('position');
    }
}
