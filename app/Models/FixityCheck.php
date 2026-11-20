<?php

namespace App\Models;

use App\Enums\FixityTrigger;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One fixity check of a media file. Append-only.
 *
 * @property int $id
 * @property string $media_file_id
 * @property string $algorithm
 * @property string $expected
 * @property string|null $actual
 * @property bool|null $matched
 * @property FixityTrigger $triggered_by
 * @property Carbon $checked_at
 * @property Carbon|null $created_at
 */
#[Fillable(['algorithm', 'expected', 'actual', 'matched', 'triggered_by', 'checked_at'])]
class FixityCheck extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'matched' => 'boolean',
            'triggered_by' => FixityTrigger::class,
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }
}
