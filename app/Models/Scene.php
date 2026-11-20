<?php

namespace App\Models;

use App\Enums\SceneKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A detected shot or a user-defined scene, with timecode in and out.
 *
 * @property int $id
 * @property string $project_id
 * @property SceneKind $kind
 * @property float $start
 * @property float $end
 * @property string|null $tc_in
 * @property string|null $tc_out
 * @property string|null $label
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kind', 'start', 'end', 'tc_in', 'tc_out', 'label', 'description'])]
class Scene extends Model
{
    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'shot',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SceneKind::class,
            'start' => 'float',
            'end' => 'float',
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
