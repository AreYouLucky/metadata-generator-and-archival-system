<?php

namespace Tests\Feature\Seeders;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\Region;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_full_location_hierarchy_from_the_dump_with_original_ids(): void
    {
        $this->seed(LocationSeeder::class);

        $this->assertSame(17, Region::count());
        $this->assertSame('NCR', Region::find(1)?->name);
        $this->assertSame('National Capital Region', Region::find(1)?->description);

        $barangay = Barangay::with('city.province.region')->findOrFail(37403);
        $this->assertSame("Engineers' Hill", $barangay->name);
        $this->assertNotNull($barangay->city?->province?->region);

        $this->assertGreaterThan(80, Province::count());
        $this->assertGreaterThan(1600, City::count());
        $this->assertGreaterThan(40000, Barangay::count());
    }

    public function test_running_the_seeder_twice_does_not_duplicate_rows(): void
    {
        $this->seed(LocationSeeder::class);
        $count = Barangay::count();

        $this->seed(LocationSeeder::class);

        $this->assertSame($count, Barangay::count());
    }
}
