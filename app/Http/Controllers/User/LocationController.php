<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ListBarangaysRequest;
use App\Http\Requests\User\ListCitiesRequest;
use App\Http\Requests\User\ListProvincesRequest;
use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Cascading Philippine location lookups for the LocationPicker (PROJECT_SPEC
 * §8). The seeded tables never change in the app, so each list is cached
 * for a day on the server and for a day in the browser.
 */
class LocationController extends Controller
{
    private const int TTL_SECONDS = 86_400;

    public function regions(): JsonResponse
    {
        return $this->respond(Cache::remember('locations:regions', self::TTL_SECONDS, fn (): array => Region::query()
            ->orderBy('id')
            ->get(['id', 'name', 'description'])
            ->map(fn (Region $region): array => [
                'id' => $region->id,
                'name' => "{$region->name} — {$region->description}",
            ])
            ->all()));
    }

    public function provinces(ListProvincesRequest $request): JsonResponse
    {
        $region = (int) $request->validated('region');

        return $this->respond(Cache::remember("locations:provinces:{$region}", self::TTL_SECONDS, fn (): array => Province::query()
            ->where('region_id', $region)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $province): array => ['id' => $province->id, 'name' => $province->name])
            ->all()));
    }

    public function cities(ListCitiesRequest $request): JsonResponse
    {
        $province = (int) $request->validated('province');

        return $this->respond(Cache::remember("locations:cities:{$province}", self::TTL_SECONDS, fn (): array => City::query()
            ->where('province_id', $province)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (City $city): array => ['id' => $city->id, 'name' => $city->name])
            ->all()));
    }

    public function barangays(ListBarangaysRequest $request): JsonResponse
    {
        $city = (int) $request->validated('city');

        return $this->respond(Cache::remember("locations:barangays:{$city}", self::TTL_SECONDS, fn (): array => Barangay::query()
            ->where('city_id', $city)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Barangay $barangay): array => ['id' => $barangay->id, 'name' => $barangay->name])
            ->all()));
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $items
     */
    private function respond(array $items): JsonResponse
    {
        return response()
            ->json(['data' => $items])
            ->header('Cache-Control', 'public, max-age='.self::TTL_SECONDS);
    }
}
