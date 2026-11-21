<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * For models with region_id / prov_id / city_id / brgy_id and the matching
 * region(), province(), city() and barangay() relations.
 */
trait HasPhilippineLocation
{
    /**
     * Eager-load just the names needed for placeLabel().
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithPlace(Builder $query): Builder
    {
        return $query->with(['barangay:id,name', 'city:id,name', 'province:id,name', 'region:id,name']);
    }

    /**
     * "Capitol Site, Cebu City, Cebu", from the most specific level chosen up.
     */
    public function placeLabel(): ?string
    {
        $parts = array_filter([
            $this->barangay?->name,
            $this->city?->name,
            $this->province?->name,
            $this->prov_id === null ? $this->region?->name : null,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
