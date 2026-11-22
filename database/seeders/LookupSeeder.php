<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\VideoType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The genres and video types encoders pick from (admins manage them from
 * Phase 4). Safe to run again: entries are matched by slug, so a run adds new
 * ones and keeps the order, without duplicating or deleting anything.
 *
 *     php artisan db:seed --class=LookupSeeder
 */
class LookupSeeder extends Seeder
{
    /**
     * Genres describe the form of the video (what kind of programme it is),
     * not its topic: topics go in subjects and keywords. Alphabetical.
     */
    public const array GENRES = [
        'Advocacy Campaign',
        'Animation / Explainer',
        'Archival Footage',
        'Ceremony',
        'Corporate / Institutional Video',
        'Cultural Performance',
        'Documentary',
        'Event Coverage',
        'Feature Story',
        'Forum / Panel Discussion',
        'Interview',
        'Lecture / Presentation',
        'Meeting',
        'Music Video',
        'News',
        'Press Conference',
        'Public Hearing',
        'Public Service Announcement',
        'Short Film',
        'Speech / Address',
        'Talk Show',
        'Testimonial',
        'Training',
        'Tutorial / How-to',
        'Vlog',
        'Webinar',
    ];

    public const array VIDEO_TYPES = [
        'Full Program',
        'Raw Footage',
        'Highlights',
        'Teaser',
        'Short Video',
        'Livestream Recording',
    ];

    public function run(): void
    {
        $this->seed(Genre::class, self::GENRES);
        $this->seed(VideoType::class, self::VIDEO_TYPES);
    }

    /**
     * @param  class-string<Genre|VideoType>  $model
     * @param  list<string>  $names
     */
    private function seed(string $model, array $names): void
    {
        foreach ($names as $index => $name) {
            $model::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $index],
            );
        }
    }
}
