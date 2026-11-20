<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An extracted key frame with its visual analysis.
 *
 * @property int $id
 * @property string $project_id
 * @property float $time_sec
 * @property string $path
 * @property bool $is_scene_start
 * @property string|null $description
 * @property string|null $ocr_text
 * @property array<int, array{label: string, confidence: float}>|null $objects
 * @property int|null $face_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['time_sec', 'path', 'is_scene_start', 'description', 'ocr_text', 'objects', 'face_count'])]
class Frame extends Model
{
    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_scene_start' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_sec' => 'float',
            'is_scene_start' => 'boolean',
            'objects' => 'array',
            'face_count' => 'integer',
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
