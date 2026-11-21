<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validation for a region → province → city/municipality → barangay chain
 * (PROJECT_SPEC §11): every level is optional, but each one given must belong
 * to the level above it, and a lower level needs its parent.
 */
final class LocationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(Request $request): array
    {
        return [
            'region_id' => ['nullable', 'integer', 'required_with:prov_id', Rule::exists('regions', 'id')],
            'prov_id' => [
                'nullable',
                'integer',
                'required_with:city_id',
                Rule::exists('provinces', 'id')->where('region_id', (int) $request->input('region_id')),
            ],
            'city_id' => [
                'nullable',
                'integer',
                'required_with:brgy_id',
                Rule::exists('cities', 'id')->where('province_id', (int) $request->input('prov_id')),
            ],
            'brgy_id' => [
                'nullable',
                'integer',
                Rule::exists('barangays', 'id')->where('city_id', (int) $request->input('city_id')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'region_id.required_with' => 'Choose the region first.',
            'prov_id.required_with' => 'Choose the province first.',
            'city_id.required_with' => 'Choose the city or municipality first.',
            'prov_id.exists' => 'That province is not in the chosen region.',
            'city_id.exists' => 'That city or municipality is not in the chosen province.',
            'brgy_id.exists' => 'That barangay is not in the chosen city or municipality.',
        ];
    }
}
