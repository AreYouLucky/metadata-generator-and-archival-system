<?php

namespace App\Models;

use Database\Factories\ProductionAgencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A producing agency, or one of its production units when parent_id is set.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $acronym
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['parent_id', 'name', 'acronym'])]
class ProductionAgency extends Model
{
    /** @use HasFactory<ProductionAgencyFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ProductionAgency, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductionAgency::class, 'parent_id');
    }

    /**
     * @return HasMany<ProductionAgency, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductionAgency::class, 'parent_id');
    }

    /**
     * @return HasMany<Record, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(Record::class, 'agency_id');
    }
}
