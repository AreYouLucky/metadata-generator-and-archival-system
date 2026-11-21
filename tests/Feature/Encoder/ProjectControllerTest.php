<?php

namespace Tests\Feature\Encoder;

use App\Models\Barangay;
use App\Models\City;
use App\Models\ProductionAgency;
use App\Models\Project;
use App\Models\ProjectSeries;
use App\Models\Province;
use App\Models\Record;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_encoder_creates_a_project(): void
    {
        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('projects.store'), [
                'title' => 'Regional S&T Fair 2026',
                'date' => '2026-07-10',
                'description' => 'Three-day fair.',
                'location' => 'Cebu City',
            ])
            ->assertCreated()
            ->assertJson(['title' => 'Regional S&T Fair 2026', 'date' => '2026-07-10', 'series' => []]);

        $this->assertDatabaseHas('projects', ['title' => 'Regional S&T Fair 2026', 'location' => 'Cebu City']);
    }

    public function test_a_project_stores_its_region_province_city_and_barangay(): void
    {
        [$region, $province, $city, $barangay] = $this->cebuCapitolSite();

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('projects.store'), [
                'title' => 'Regional S&T Fair 2026',
                'region_id' => $region->id,
                'prov_id' => $province->id,
                'city_id' => $city->id,
                'brgy_id' => $barangay->id,
            ])
            ->assertCreated()
            ->assertJsonPath('place', 'Capitol Site, Cebu City, Cebu');

        $this->assertDatabaseHas('projects', ['title' => 'Regional S&T Fair 2026', 'brgy_id' => $barangay->id]);
    }

    public function test_a_location_that_does_not_belong_to_the_level_above_is_refused(): void
    {
        [$region, $province, $city] = $this->cebuCapitolSite();
        $otherRegion = Region::create(['name' => 'NCR', 'description' => 'National Capital Region']);
        $otherCity = City::create(['province_id' => Province::create(['region_id' => $otherRegion->id, 'name' => 'Metro Manila'])->id, 'name' => 'Manila']);

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('projects.store'), [
                'title' => 'Mismatched',
                'region_id' => $otherRegion->id,
                'prov_id' => $province->id,
                'city_id' => $otherCity->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'prov_id' => 'That province is not in the chosen region.',
                'city_id' => 'That city or municipality is not in the chosen province.',
            ]);

        $this->postJson(route('projects.store'), ['title' => 'Orphan city', 'city_id' => $city->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prov_id' => 'Choose the province first.']);
    }

    public function test_a_multi_day_project_with_agency_and_thumbnail_records_its_creator(): void
    {
        Storage::fake('media');
        $encoder = User::factory()->encoder()->create();
        $agency = ProductionAgency::factory()->create(['name' => 'Department of Science and Technology', 'acronym' => 'DOST']);

        $response = $this->actingAs($encoder)
            ->post(route('projects.store'), [
                'title' => 'Regional S&T Fair 2026',
                'date' => '2026-07-10',
                'end_date' => '2026-07-12',
                'agency_id' => $agency->id,
                'thumbnail' => $this->png('cover.png'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('end_date', '2026-07-12')
            ->assertJsonPath('agency.acronym', 'DOST');

        $project = Project::findOrFail($response->json('id'));
        $this->assertSame($encoder->id, $project->created_by);
        $this->assertNotNull($project->thumbnail_path);
        Storage::disk('media')->assertExists($project->thumbnail_path);
        $this->assertStringStartsWith("projects/{$project->id}/thumbnail-", $project->thumbnail_path);

        $this->get($response->json('thumbnail_url'))->assertOk();
    }

    public function test_an_event_cannot_end_before_it_starts_and_units_are_not_project_agencies(): void
    {
        $unit = ProductionAgency::factory()->create(['parent_id' => ProductionAgency::factory()->create()->id]);

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('projects.store'), ['title' => 'Backwards', 'date' => '2026-07-12', 'end_date' => '2026-07-10', 'agency_id' => $unit->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'end_date' => 'The last day cannot be before the first day.',
                'agency_id' => 'Choose an agency from the list.',
            ]);
    }

    public function test_the_creator_edits_a_project_and_replaces_its_thumbnail(): void
    {
        Storage::fake('media');
        $encoder = User::factory()->encoder()->create();
        $project = Project::factory()->create(['title' => 'Old name']);
        $project->created_by = $encoder->id;
        $project->thumbnail_path = "projects/{$project->id}/thumbnail-old.png";
        $project->save();
        Storage::disk('media')->put($project->thumbnail_path, 'old');

        $this->actingAs($encoder)
            ->post(route('projects.update', $project), [
                '_method' => 'PATCH',
                'title' => 'New name',
                'thumbnail' => $this->png('new.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('title', 'New name');

        Storage::disk('media')->assertMissing("projects/{$project->id}/thumbnail-old.png");
        $this->assertNotSame("projects/{$project->id}/thumbnail-old.png", $project->refresh()->thumbnail_path);
    }

    public function test_another_encoder_cannot_edit_the_project_but_an_admin_can(): void
    {
        $project = Project::factory()->create();
        $project->created_by = User::factory()->encoder()->create()->id;
        $project->save();

        $this->actingAs(User::factory()->encoder()->create())
            ->patchJson(route('projects.update', $project), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('projects.update', $project), ['title' => 'Fixed by admin'])
            ->assertOk();

        $this->assertSame('Fixed by admin', $project->refresh()->title);
    }

    public function test_the_creator_soft_deletes_an_empty_project_and_it_leaves_the_lists(): void
    {
        $encoder = User::factory()->encoder()->create();
        $project = Project::factory()->create(['title' => 'Cancelled Fair']);
        $project->created_by = $encoder->id;
        $project->save();

        $this->actingAs($encoder)
            ->deleteJson(route('projects.destroy', $project))
            ->assertOk();

        $this->assertSoftDeleted($project);
        $this->get(route('projects.show', $project))->assertNotFound();
        $this->getJson(route('projects.options'))->assertJsonCount(0, 'data');
        $this->get(route('projects.index'))
            ->assertInertia(fn (Assert $page) => $page->has('projects.data', 0));
    }

    public function test_a_project_with_videos_is_deleted_only_by_an_admin_and_its_videos_keep_the_label(): void
    {
        $encoder = User::factory()->encoder()->create();
        $project = Project::factory()->create(['title' => 'Science Fair']);
        $project->created_by = $encoder->id;
        $project->save();
        $record = Record::factory()->for($encoder, 'creator')->for($project)->create();

        $this->actingAs(User::factory()->encoder()->create())
            ->deleteJson(route('projects.destroy', $project))
            ->assertForbidden();

        $this->actingAs($encoder)
            ->deleteJson(route('projects.destroy', $project))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson(route('projects.destroy', $project))
            ->assertOk();

        $this->assertSoftDeleted($project);
        $this->assertSame('Science Fair', $record->refresh()->project?->title);
    }

    public function test_a_series_is_edited_by_whoever_may_edit_its_project(): void
    {
        $encoder = User::factory()->encoder()->create();
        $project = Project::factory()->create();
        $project->created_by = $encoder->id;
        $project->save();
        $series = ProjectSeries::factory()->for($project)->create();

        $this->actingAs(User::factory()->encoder()->create())
            ->patchJson(route('project-series.update', $series), ['title' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($encoder)
            ->patchJson(route('project-series.update', $series), ['title' => 'Day 2', 'date' => '2026-07-11', 'end_date' => '2026-07-11'])
            ->assertOk();

        $this->assertSame('Day 2', $series->refresh()->title);
    }

    public function test_a_project_needs_a_title_and_a_real_date(): void
    {
        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('projects.store'), ['title' => '', 'date' => 'next tuesday-ish'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'date']);
    }

    public function test_a_series_is_added_to_a_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('projects.series.store', $project), ['title' => 'Day 1', 'date' => '2026-07-10'])
            ->assertCreated()
            ->assertJson(['title' => 'Day 1']);

        $this->assertSame(['Day 1'], $project->series()->pluck('title')->all());
    }

    public function test_the_picker_finds_projects_with_their_series(): void
    {
        $fair = Project::factory()->create(['title' => 'Science Fair']);
        ProjectSeries::factory()->for($fair)->create(['title' => 'Day 1']);
        Project::factory()->create(['title' => 'Budget Hearing']);

        $this->actingAs(User::factory()->encoder()->create())
            ->getJson(route('projects.options', ['q' => 'science']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fair->id)
            ->assertJsonPath('data.0.series.0.title', 'Day 1');
    }

    public function test_a_project_page_lists_its_series_and_only_the_encoders_own_records(): void
    {
        $encoder = User::factory()->encoder()->create();
        $project = Project::factory()->create();
        ProjectSeries::factory()->for($project)->create(['title' => 'Day 1']);
        $own = Record::factory()->for($encoder, 'creator')->for($project)->create();
        Record::factory()->for($project)->create();

        $this->actingAs($encoder)
            ->get(route('projects.show', $project))
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/projects/show-page/show')
                ->where('project.id', $project->id)
                ->has('series', 1)
                ->has('records.data', 1)
                ->where('records.data.0.id', $own->id));
    }

    public function test_the_project_list_is_searchable_and_counts_series(): void
    {
        $fair = Project::factory()->create(['title' => 'Science Fair']);
        ProjectSeries::factory()->count(2)->for($fair)->create();
        Project::factory()->create(['title' => 'Budget Hearing']);

        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('projects.index', ['q' => 'fair']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/projects/index-page/index')
                ->has('projects.data', 1)
                ->where('projects.data.0.series_count', 2));
    }

    public function test_viewers_cannot_see_or_create_projects(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('projects.index'))->assertForbidden();
        $this->actingAs($viewer)->postJson(route('projects.store'), ['title' => 'Nope'])->assertForbidden();
    }

    /**
     * A real 1×1 PNG (the GD extension, which fake images need, isn't installed everywhere).
     */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true),
        );
    }

    /**
     * @return array{0: Region, 1: Province, 2: City, 3: Barangay}
     */
    private function cebuCapitolSite(): array
    {
        $region = Region::create(['name' => 'Region VII', 'description' => 'Central Visayas']);
        $province = Province::create(['region_id' => $region->id, 'name' => 'Cebu']);
        $city = City::create(['province_id' => $province->id, 'name' => 'Cebu City']);

        return [$region, $province, $city, Barangay::create(['city_id' => $city->id, 'name' => 'Capitol Site'])];
    }
}
