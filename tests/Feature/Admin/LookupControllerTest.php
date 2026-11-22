<?php

namespace Tests\Feature\Admin;

use App\Models\Genre;
use App\Models\Record;
use App\Models\User;
use App\Models\VideoType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admin CRUD for the genre and video type lists (they share one service).
 */
class LookupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_shows_items_in_order_with_their_record_counts(): void
    {
        $second = Genre::factory()->create(['name' => 'Documentary', 'sort_order' => 2]);
        $first = Genre::factory()->create(['name' => 'Interview', 'sort_order' => 1]);
        Record::factory()->create(['genre_id' => $second->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.genres.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/genres-page/genres')
                ->where('items.0.id', $first->id)
                ->where('items.1.records_count', 1));
    }

    public function test_adding_generates_a_unique_slug(): void
    {
        $admin = User::factory()->admin()->create();
        VideoType::factory()->create(['name' => 'Raw-footage', 'slug' => 'raw-footage']);

        $this->actingAs($admin)
            ->postJson(route('admin.video-types.store'), ['name' => 'Raw footage', 'sort_order' => 3])
            ->assertCreated()
            ->assertJsonPath('slug', 'raw-footage-2')
            ->assertJsonPath('sort_order', 3);
    }

    public function test_a_duplicate_name_is_refused(): void
    {
        Genre::factory()->create(['name' => 'News']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.genres.store'), ['name' => 'News'])
            ->assertJsonValidationErrors(['name' => 'There is already a genre with this name.']);
    }

    public function test_renaming_keeps_the_slug_so_public_links_keep_working(): void
    {
        $genre = Genre::factory()->create(['name' => 'Docu', 'slug' => 'docu']);

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('admin.genres.update', $genre), ['name' => 'Documentary', 'sort_order' => 5])
            ->assertOk();

        $genre->refresh();
        $this->assertSame('Documentary', $genre->name);
        $this->assertSame('docu', $genre->slug);
        $this->assertSame(5, $genre->sort_order);
    }

    public function test_an_item_in_use_cannot_be_deleted_but_an_unused_one_can(): void
    {
        $admin = User::factory()->admin()->create();
        $used = VideoType::factory()->create();
        $unused = VideoType::factory()->create();
        Record::factory()->create(['video_type_id' => $used->id]);

        $this->actingAs($admin)->deleteJson(route('admin.video-types.destroy', $used))->assertUnprocessable();
        $this->actingAs($admin)->deleteJson(route('admin.video-types.destroy', $unused))->assertOk();

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    public function test_encoders_cannot_manage_the_lists(): void
    {
        $encoder = User::factory()->encoder()->create();

        $this->actingAs($encoder)->get(route('admin.genres.index'))->assertForbidden();
        $this->actingAs($encoder)->postJson(route('admin.genres.store'), ['name' => 'Talk'])->assertForbidden();

        $this->assertDatabaseMissing('genres', ['name' => 'Talk']);
    }
}
