<?php

namespace Database\Seeders;

use App\Models\ProductionAgency;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed Philippine locations, one account per role and a sample agency.
     * Development only: every account uses the password "password".
     */
    public function run(): void
    {
        $this->call(LocationSeeder::class);

        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        User::factory()->encoder()->create([
            'name' => 'Encoder',
            'email' => 'encoder@example.com',
        ]);

        User::factory()->create([
            'name' => 'Viewer',
            'email' => 'user@example.com',
        ]);

        $agency = ProductionAgency::factory()->create([
            'name' => 'Department of Science and Technology',
            'acronym' => 'DOST',
        ]);

        ProductionAgency::factory()->unitOf($agency)->create([
            'name' => 'Science and Technology Information Institute',
            'acronym' => 'STII',
        ]);
    }
}
