<?php

namespace Tests\Feature\Admin;

use App\Models\ProductionAgency;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgencyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_adds_an_agency_and_one_of_its_units(): void
    {
        $admin = User::factory()->admin()->create();

        $agencyId = $this->actingAs($admin)
            ->postJson(route('admin.agencies.store'), ['name' => 'Department of Science and Technology', 'acronym' => 'DOST'])
            ->assertCreated()
            ->json('id');

        $this->postJson(route('admin.agencies.store'), ['name' => 'Science and Technology Information Institute', 'acronym' => 'STII', 'parent_id' => $agencyId])
            ->assertCreated();

        $this->get(route('agencies.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/agencies-page/agencies')
                ->where('canManage', true)
                ->where('agencies.0.acronym', 'DOST')
                ->where('agencies.0.units.0.acronym', 'STII'));
    }

    public function test_units_are_one_level_deep_and_names_are_unique_within_a_level(): void
    {
        $agency = ProductionAgency::factory()->create(['name' => 'DOST']);
        $unit = ProductionAgency::factory()->create(['name' => 'STII', 'parent_id' => $agency->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.agencies.store'), ['name' => 'STII', 'parent_id' => $agency->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'There is already one with this name here.']);

        $this->postJson(route('admin.agencies.store'), ['name' => 'Sub-unit', 'parent_id' => $unit->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id' => 'Units can only be added to a top-level agency.']);
    }

    public function test_an_agency_is_renamed(): void
    {
        $agency = ProductionAgency::factory()->create(['name' => 'Old name']);

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('admin.agencies.update', $agency), ['name' => 'New name', 'acronym' => 'NN'])
            ->assertOk();

        $this->assertSame('New name', $agency->refresh()->name);
    }

    public function test_an_agency_used_by_a_record_cannot_be_deleted_but_an_unused_one_can(): void
    {
        $used = ProductionAgency::factory()->create();
        Record::factory()->create(['agency_id' => $used->id]);
        $unused = ProductionAgency::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->deleteJson(route('admin.agencies.destroy', $used))->assertUnprocessable();
        $this->actingAs($admin)->deleteJson(route('admin.agencies.destroy', $unused))->assertOk();

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    public function test_encoders_read_the_agency_list_but_cannot_manage_it(): void
    {
        $agency = ProductionAgency::factory()->create(['name' => 'DOST']);
        ProductionAgency::factory()->create(['name' => 'STII', 'parent_id' => $agency->id]);
        $encoder = User::factory()->encoder()->create();

        $this->actingAs($encoder)
            ->getJson(route('agencies.options'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'DOST')
            ->assertJsonPath('data.0.units.0.name', 'STII');

        $this->get(route('agencies.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/agencies-page/agencies')
                ->where('agencies.0.units.0.name', 'STII')
                ->where('canManage', false));

        $this->postJson(route('admin.agencies.store'), ['name' => 'Rogue'])->assertForbidden();
    }
}
