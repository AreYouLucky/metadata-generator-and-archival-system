<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An event or programme that groups records.
 *
 * @property string $id
 * @property string $title
 * @property Carbon|null $date
 * @property string|null $description
 * @property string|null $location
 * @property int|null $region_id
 * @property int|null $prov_id
 * @property int|null $city_id
 * @property int|null $brgy_id
 * @property int|null $agency_id
 * @property string|null $thumbnail_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'date', 'description', 'location', 'region_id', 'prov_id', 'city_id', 'brgy_id', 'agency_id'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'prov_id');
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * @return BelongsTo<Barangay, $this>
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'brgy_id');
    }

    /**
     * @return BelongsTo<ProductionAgency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(ProductionAgency::class, 'agency_id');
    }

    /**
     * @return HasMany<ProjectSeries, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(ProjectSeries::class)->orderBy('date');
    }

    /**
     * @return HasMany<Record, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }
}
