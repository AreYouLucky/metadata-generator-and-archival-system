<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A speaker label found by diarization (S1, S2...) and the name an encoder gives it.
 *
 * @property int $id
 * @property string $record_id
 * @property string $label
 * @property string|null $display_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['label', 'display_name'])]
class RecordSpeaker extends Model
{
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
        return $this->hasMany(RecordSegment::class);
    }
}
