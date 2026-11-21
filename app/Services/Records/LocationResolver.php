<?php

namespace App\Services\Records;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\Region;

/**
 * Matches place names (from an AI suggestion) against the seeded Philippine
 * location tables, top-down: each level is looked up inside the one above,
 * so the result is always a valid chain. Matching is case-insensitive and
 * exact; a level that doesn't match stops the chain there.
 */
class LocationResolver
{
    /**
     * @param  array{region?: string|null, province?: string|null, city?: string|null, barangay?: string|null}  $names
     * @return array{region_id: int|null, prov_id: int|null, city_id: int|null, brgy_id: int|null}
     */
    public function resolve(array $names): array
    {
        $ids = ['region_id' => null, 'prov_id' => null, 'city_id' => null, 'brgy_id' => null];

        $region = self::clean($names['region'] ?? null);
        if ($region !== null) {
            $ids['region_id'] = Region::query()
                ->whereRaw('lower(name) = ?', [$region])
                ->orWhereRaw('lower(description) = ?', [$region])
                ->value('id');
        }

        $province = self::clean($names['province'] ?? null);
        if ($province !== null) {
            $ids['prov_id'] = Province::query()
                ->when($ids['region_id'], fn ($query, $id) => $query->where('region_id', $id))
                ->whereRaw('lower(name) = ?', [$province])
                ->value('id');
        }

        $city = self::clean($names['city'] ?? null);
        if ($city !== null && ($ids['prov_id'] !== null || $province === null)) {
            $match = City::query()
                ->when($ids['prov_id'], fn ($query, $id) => $query->where('province_id', $id))
                ->whereRaw('lower(name) = ?', [$city])
                ->first(['id', 'province_id']);
            $ids['city_id'] = $match?->id;

            // A city named without its province still gives the chain above it.
            if ($match !== null && $ids['prov_id'] === null) {
                $ids['prov_id'] = $match->province_id;
                $ids['region_id'] ??= Province::query()->whereKey($match->province_id)->value('region_id');
            }
        }

        $barangay = self::clean($names['barangay'] ?? null);
        if ($barangay !== null && $ids['city_id'] !== null) {
            $ids['brgy_id'] = Barangay::query()
                ->where('city_id', $ids['city_id'])
                ->whereRaw('lower(name) = ?', [$barangay])
                ->value('id');
        }

        // Never return a level without its parent.
        if ($ids['region_id'] === null) {
            return ['region_id' => null, 'prov_id' => null, 'city_id' => null, 'brgy_id' => null];
        }

        return $ids;
    }

    private static function clean(?string $name): ?string
    {
        $name = $name === null ? '' : mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? ''));

        return $name === '' ? null : $name;
    }
}
