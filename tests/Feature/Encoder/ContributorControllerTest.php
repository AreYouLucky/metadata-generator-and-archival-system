<?php

namespace Tests\Feature\Encoder;

use App\Models\Record;
use App\Models\RecordContributor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContributorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_is_saved_in_order_keeping_edited_rows_and_removing_missing_ones(): void
    {
        $record = Record::factory()->create();
        $host = RecordContributor::factory()->for($record)->create(['role' => 'presenter', 'name' => 'Ana Reyes', 'sort_order' => 0]);
        $removed = RecordContributor::factory()->for($record)->create(['role' => 'editor', 'name' => 'Old Editor', 'sort_order' => 1]);

        $this->actingAs($record->creator)
            ->putJson(route('records.contributors.sync', $record), ['contributors' => [
                ['role' => 'cast', 'name' => '  Juan Dela Cruz '],
                ['id' => $host->id, 'role' => 'presenter', 'name' => 'Ana M. Reyes'],
            ]])
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'Juan Dela Cruz')
            ->assertJsonPath('0.role', 'cast')
            ->assertJsonPath('1.id', $host->id)
            ->assertJsonPath('1.name', 'Ana M. Reyes');

        $this->assertModelMissing($removed);
        $this->assertSame(1, $host->refresh()->sort_order);
    }

    public function test_an_empty_list_removes_every_credit(): void
    {
        $contributor = RecordContributor::factory()->create();
        $record = $contributor->record;

        $this->actingAs($record->creator)
            ->putJson(route('records.contributors.sync', $record), ['contributors' => []])
            ->assertOk()
            ->assertExactJson([]);

        $this->assertModelMissing($contributor);
    }

    public function test_a_credit_of_another_record_cannot_be_taken_over(): void
    {
        $record = Record::factory()->create();
        $foreign = RecordContributor::factory()->for(Record::factory()->for($record->creator, 'creator'))->create(['name' => 'Elsewhere']);

        $this->actingAs($record->creator)
            ->putJson(route('records.contributors.sync', $record), ['contributors' => [
                ['id' => $foreign->id, 'role' => 'cast', 'name' => 'Hijacked'],
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contributors.0.id']);

        $this->assertSame('Elsewhere', $foreign->refresh()->name);
    }

    public function test_an_unknown_role_and_a_missing_name_are_rejected(): void
    {
        $record = Record::factory()->create();

        $this->actingAs($record->creator)
            ->putJson(route('records.contributors.sync', $record), ['contributors' => [
                ['role' => 'stunt_double', 'name' => 'Someone'],
                ['role' => 'cast', 'name' => ''],
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'contributors.0.role' => 'The selected contributors.0.role is invalid.',
                'contributors.1.name' => 'Every credit needs a name.',
            ]);

        $this->assertSame(0, $record->contributors()->count());
    }

    public function test_another_encoder_cannot_change_the_credits(): void
    {
        $contributor = RecordContributor::factory()->create();
        $record = $contributor->record;

        $this->actingAs(User::factory()->encoder()->create())
            ->putJson(route('records.contributors.sync', $record), ['contributors' => []])
            ->assertForbidden();

        $this->assertModelExists($contributor);
    }

    public function test_the_workspace_lists_the_credits_and_the_roles(): void
    {
        $contributor = RecordContributor::factory()->create(['role' => 'presenter', 'name' => 'Ana Reyes']);
        $record = $contributor->record;

        $this->actingAs($record->creator)
            ->get(route('records.show', $record))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contributors', [['id' => $contributor->id, 'role' => 'presenter', 'name' => 'Ana Reyes']])
                ->where('contributorRoles.4', ['value' => 'presenter', 'label' => 'Presenter / Host']));
    }
}
