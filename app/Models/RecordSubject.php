<?php

namespace App\Models;

use App\Enums\MetadataSource;
use App\Enums\SubjectType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A subject (who or what the record is about). AI suggestions stay unconfirmed until accepted.
 *
 * @property int $id
 * @property string $record_id
 * @property string $subject
 * @property SubjectType $type
 * @property MetadataSource $source
 * @property bool $is_confirmed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['subject', 'type', 'source', 'is_confirmed'])]
class RecordSubject extends Model
{
    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'topic',
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
            'type' => SubjectType::class,
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
