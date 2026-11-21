<?php

namespace Tests\Feature\User;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_level_lists_only_the_children_of_the_chosen_parent(): void
    {
        $visayas = Region::create(['name' => 'Region VII', 'description' => 'Central Visayas']);
        $ncr = Region::create(['name' => 'NCR', 'description' => 'National Capital Region']);
        $cebu = Province::create(['region_id' => $visayas->id, 'name' => 'Cebu']);
        Province::create(['region_id' => $ncr->id, 'name' => 'Metro Manila']);
        $cebuCity = City::create(['province_id' => $cebu->id, 'name' => 'Cebu City']);
        Barangay::create(['city_id' => $cebuCity->id, 'name' => 'Capitol Site']);

        $this->getJson(route('locations.regions'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=86400, public')
            ->assertJsonPath('data.0.name', 'Region VII — Central Visayas');
        $this->getJson(route('locations.provinces', ['region' => $visayas->id]))
            ->assertExactJson(['data' => [['id' => $cebu->id, 'name' => 'Cebu']]]);
        $this->getJson(route('locations.cities', ['province' => $cebu->id]))
            ->assertJsonPath('data.0.name', 'Cebu City');
        $this->getJson(route('locations.barangays', ['city' => $cebuCity->id]))
            ->assertJsonPath('data.0.name', 'Capitol Site');
    }

    public function test_a_missing_or_malformed_parent_is_rejected(): void
    {
        $this->getJson(route('locations.provinces'))->assertUnprocessable()->assertJsonValidationErrors(['region']);
        $this->getJson(route('locations.barangays', ['city' => 'abc']))->assertUnprocessable()->assertJsonValidationErrors(['city']);
    }
}
