<?php

namespace App\Models;

use Database\Factories\CatalogueEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An event (ceremony, conference, programme) that videos can be grouped under.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $location
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'starts_on', 'ends_on', 'location', 'description'])]
class CatalogueEvent extends Model
{
    /** @use HasFactory<CatalogueEventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
