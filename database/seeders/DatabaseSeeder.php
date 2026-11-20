<?php

namespace Database\Seeders;

use App\Models\CatalogueEvent;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with one account per role and sample
     * browse data. Development only: every account uses the password "password".
     */
    public function run(): void
    {
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

        foreach (['News', 'Documentaries', 'Events', 'Interviews'] as $index => $name) {
            Category::factory()->create([
                'name' => $name,
                'slug' => str($name)->slug()->toString(),
                'sort_order' => $index,
            ]);
        }

        CatalogueEvent::factory()->count(3)->create();
    }
}
