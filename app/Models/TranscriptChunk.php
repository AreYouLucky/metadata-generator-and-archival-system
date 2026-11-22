<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ~45 s passage of a record's original transcript, with its embedding for
 * chat search (Phase 7). Built and refreshed by TranscriptIndex when the chat
 * first searches the transcript, so edits to the transcript are picked up.
 *
 * `embedding` is base64 of little-endian float32 values (no pgvector on the
 * host PostgreSQL; search is within one record, see DECISIONS.md).
 *
 * @property int $id
 * @property string $record_id
 * @property int $record_transcript_id
 * @property float $start
 * @property float $end
 * @property string $text
 * @property string $text_hash
 * @property string|null $embedding
 * @property string|null $embedding_model
 * @property int|null $embedding_dimensions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['record_transcript_id', 'start', 'end', 'text', 'text_hash', 'embedding', 'embedding_model', 'embedding_dimensions'])]
class TranscriptChunk extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start' => 'float',
            'end' => 'float',
            'embedding_dimensions' => 'integer',
        ];
    }

    /**
     * The stored vector, or null when it hasn't been embedded (yet).
     *
     * @return list<float>|null
     */
    public function vector(): ?array
    {
        if ($this->embedding === null) {
            return null;
        }

        $binary = base64_decode($this->embedding, true);

        if ($binary === false || $binary === '' || strlen($binary) % 4 !== 0) {
            return null;
        }

        /** @var array<int, float> $values */
        $values = unpack('g*', $binary);

        return array_values($values);
    }

    /**
     * @param  list<float>  $vector
     */
    public static function encodeVector(array $vector): string
    {
        return base64_encode(pack('g*', ...$vector));
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /**
     * @return BelongsTo<RecordTranscript, $this>
     */
    public function transcript(): BelongsTo
    {
        return $this->belongsTo(RecordTranscript::class, 'record_transcript_id');
    }
}
