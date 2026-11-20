<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\ProductionAgency;
use App\Models\User;
use App\Models\VideoType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed Philippine locations, one account per role, a sample agency, and
     * starter genres and video types.
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

        $this->seedLookup(Genre::class, ['Documentary', 'News', 'Event Coverage', 'Interview', 'Training', 'Public Service Announcement']);
        $this->seedLookup(VideoType::class, ['Full Program', 'Raw Footage', 'Highlights', 'Teaser', 'Short Video', 'Livestream Recording']);
    }

    /**
     * @param  class-string<Genre|VideoType>  $model
     * @param  list<string>  $names
     */
    private function seedLookup(string $model, array $names): void
    {
        foreach ($names as $index => $name) {
            $model::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $index],
            );
        }
    }
}
