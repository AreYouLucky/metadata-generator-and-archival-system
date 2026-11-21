<?php

namespace Tests\Feature\Encoder;

use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_encoder_sees_only_their_own_submitted_records(): void
    {
        $encoder = User::factory()->encoder()->create();
        $own = Record::factory()->for($encoder, 'creator')->submitted()->create();
        Record::factory()->for($encoder, 'creator')->create();
        Record::factory()->submitted()->create();

        $this->actingAs($encoder)
            ->get(route('reviews.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/reviews-page/reviews')
                ->has('records.data', 1)
                ->where('records.data.0.id', $own->id)
                ->where('records.data.0.creator', null)
                ->where('showCreator', false));
    }

    public function test_an_admin_sees_every_submitted_record_oldest_first(): void
    {
        $newer = Record::factory()->submitted()->create(['submitted_at' => now()->subDay()]);
        $older = Record::factory()->submitted()->create(['submitted_at' => now()->subDays(3)]);
        Record::factory()->approved()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('reviews.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 2)
                ->where('records.data.0.id', $older->id)
                ->where('records.data.1.id', $newer->id)
                ->where('showCreator', true));
    }

    public function test_viewers_cannot_open_the_queue(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reviews.index'))
            ->assertForbidden();
    }
}
