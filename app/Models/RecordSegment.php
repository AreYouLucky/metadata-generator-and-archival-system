<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Database\Factories\RecordSegmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One timed line of a transcript. original_text keeps what transcription produced.
 *
 * @property int $id
 * @property int $record_transcript_id
 * @property int $position
 * @property float $start
 * @property float $end
 * @property string $text
 * @property string $original_text
 * @property int|null $record_speaker_id
 * @property bool $is_edited
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['position', 'start', 'end', 'text', 'original_text', 'record_speaker_id', 'is_edited'])]
class RecordSegment extends Model
{
    /** @use HasFactory<RecordSegmentFactory> */
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
     * @return BelongsTo<RecordTranscript, $this>
     */
    public function transcript(): BelongsTo
    {
        return $this->belongsTo(RecordTranscript::class, 'record_transcript_id');
    }

    /**
     * @return BelongsTo<RecordSpeaker, $this>
     */
    public function speaker(): BelongsTo
    {
        return $this->belongsTo(RecordSpeaker::class, 'record_speaker_id');
    }
}
