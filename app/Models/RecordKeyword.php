<?php

namespace App\Models;

use App\Enums\MetadataSource;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A keyword attached to a record. AI suggestions stay unconfirmed until accepted.
 *
 * @property int $id
 * @property string $record_id
 * @property string $keyword
 * @property MetadataSource $source
 * @property bool $is_confirmed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['keyword', 'source', 'is_confirmed'])]
class RecordKeyword extends Model
{
    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'manual',
        'is_confirmed' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => MetadataSource::class,
            'is_confirmed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }
}
