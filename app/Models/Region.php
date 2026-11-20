<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Philippine region (seeded from updated_phil.sql).
 *
 * @property int $id
 * @property string $name
 * @property string $description
 */
#[Fillable(['name', 'description'])]
class Region extends Model
{
    public $timestamps = false;

    /**
     * @return HasMany<Province, $this>
     */
    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }
}
