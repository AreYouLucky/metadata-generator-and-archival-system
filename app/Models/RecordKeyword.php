<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A keyword attached to a record.
 *
 * @property int $id
 * @property string $record_id
 * @property string $keyword
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['keyword'])]
class RecordKeyword extends Model
{
    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }
}
