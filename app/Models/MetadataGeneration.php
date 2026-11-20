<?php

namespace App\Models;

use App\Enums\Preset;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One AI metadata generation; history is kept and one is active per project.
 *
 * @property int $id
 * @property string $project_id
 * @property Preset $preset
 * @property string|null $custom_prompt
 * @property string $model
 * @property array<string, mixed> $payload
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['preset', 'custom_prompt', 'model', 'payload', 'is_active'])]
class MetadataGeneration extends Model
{
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
