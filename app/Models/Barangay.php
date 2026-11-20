<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A barangay within a city or municipality.
 *
 * @property int $id
 * @property int $city_id
 * @property string $name
 */
#[Fillable(['city_id', 'name'])]
class Barangay extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
