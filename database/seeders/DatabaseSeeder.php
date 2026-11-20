<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\ProductionAgency;
use App\Models\VideoType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed Philippine locations, the accounts (see UserSeeder), a sample agency,
     * and starter genres and video types. Safe to run more than once.
     */
    public function run(): void
    {
        $this->call([LocationSeeder::class, UserSeeder::class]);

        $agency = ProductionAgency::query()->firstOrCreate(
            ['parent_id' => null, 'name' => 'Department of Science and Technology'],
            ['acronym' => 'DOST'],
        );

        ProductionAgency::query()->firstOrCreate(
            ['parent_id' => $agency->id, 'name' => 'Science and Technology Information Institute'],
            ['acronym' => 'STII'],
        );

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
