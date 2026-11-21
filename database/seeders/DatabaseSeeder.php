<?php

namespace Database\Seeders;

use App\Models\ProductionAgency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed Philippine locations, the accounts (see UserSeeder), a sample agency,
     * and the genres and video types (LookupSeeder). Safe to run more than once.
     */
    public function run(): void
    {
        $this->call([LocationSeeder::class, UserSeeder::class, LookupSeeder::class]);

        $agency = ProductionAgency::query()->firstOrCreate(
            ['parent_id' => null, 'name' => 'Department of Science and Technology'],
            ['acronym' => 'DOST'],
        );

        ProductionAgency::query()->firstOrCreate(
            ['parent_id' => $agency->id, 'name' => 'Science and Technology Information Institute'],
            ['acronym' => 'STII'],
        );
    }
}
