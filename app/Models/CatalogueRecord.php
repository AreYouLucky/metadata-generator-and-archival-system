<?php

namespace App\Models;

use App\Enums\DatePrecision;
use App\Enums\MetadataSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Descriptive metadata for a project (what is this?).
 *
 * @property int $id
 * @property string $project_id
 * @property string|null $description
 * @property Carbon|null $date_shot
 * @property DatePrecision|null $date_shot_precision
 * @property MetadataSource|null $date_shot_source
 * @property string|null $location_name
 * @property float|null $location_lat
 * @property float|null $location_lng
 * @property MetadataSource|null $location_source
 * @property string|null $series_title
 * @property int|null $season_number
 * @property int|null $episode_number
 * @property string|null $episode_title
 * @property array<int, string>|null $keywords
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['description', 'date_shot', 'date_shot_precision', 'date_shot_source', 'location_name', 'location_lat', 'location_lng', 'location_source', 'series_title', 'season_number', 'episode_number', 'episode_title', 'keywords'])]
class CatalogueRecord extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_shot' => 'date',
            'date_shot_precision' => DatePrecision::class,
            'date_shot_source' => MetadataSource::class,
            'location_lat' => 'float',
            'location_lng' => 'float',
            'location_source' => MetadataSource::class,
            'season_number' => 'integer',
            'episode_number' => 'integer',
            'keywords' => 'array',
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
