<?php

namespace App\Models;

use Database\Factories\RecordSegmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
 * @property string|null $speaker
 * @property bool $is_edited
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['position', 'start', 'end', 'text', 'original_text', 'speaker', 'is_edited'])]
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
}
