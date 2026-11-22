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

    public function test_admins_are_sent_to_the_approval_queue(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('reviews.index'))
            ->assertRedirect(route('admin.approvals.index'));
    }

    public function test_viewers_cannot_open_the_queue(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reviews.index'))
            ->assertForbidden();
    }
}
