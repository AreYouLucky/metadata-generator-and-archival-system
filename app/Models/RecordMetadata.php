<?php

namespace App\Models;

use App\Enums\Preset;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One AI metadata generation for a record; history is kept and one is active.
 *
 * @property int $id
 * @property string $record_id
 * @property Preset $preset
 * @property string|null $custom_prompt
 * @property string $model
 * @property array<string, mixed> $payload
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['preset', 'custom_prompt', 'model', 'payload', 'is_active'])]
class RecordMetadata extends Model
{
    protected $table = 'record_metadata';

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preset' => Preset::class,
            'payload' => 'array',
            'is_active' => 'boolean',
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
