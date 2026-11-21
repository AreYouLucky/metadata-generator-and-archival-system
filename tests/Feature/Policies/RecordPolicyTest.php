<?php

namespace Tests\Feature\Policies;

use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_creating_encoder_can_view_and_edit_a_draft(): void
    {
        $record = Record::factory()->create();
        $owner = $record->creator;

        $this->assertTrue($owner->can('view', $record));
        $this->assertTrue($owner->can('update', $record));
        $this->assertTrue($owner->can('generate', $record));
        $this->assertTrue($owner->can('export', $record));
    }

    public function test_another_encoder_can_neither_view_nor_edit_the_record(): void
    {
        $record = Record::factory()->create();
        $stranger = User::factory()->encoder()->create();

        $this->assertFalse($stranger->can('view', $record));
        $this->assertFalse($stranger->can('update', $record));
        $this->assertFalse($stranger->can('export', $record));
    }

    public function test_the_encoder_can_no_longer_edit_a_submitted_record_but_an_admin_can(): void
    {
        $record = Record::factory()->submitted()->create();

        $this->assertTrue($record->creator->can('view', $record));
        $this->assertFalse($record->creator->can('update', $record));
        $this->assertTrue(User::factory()->admin()->create()->can('update', $record));
    }

    public function test_viewers_and_deactivated_accounts_have_no_access(): void
    {
        $record = Record::factory()->create();
        $deactivatedOwner = $record->creator;
        $deactivatedOwner->is_active = false;
        $deactivatedOwner->save();

        $viewer = User::factory()->create();

        $this->assertFalse($viewer->can('viewAny', Record::class));
        $this->assertFalse($viewer->can('view', $record));
        $this->assertFalse($deactivatedOwner->can('view', $record));
        $this->assertFalse(User::factory()->admin()->inactive()->create()->can('view', $record));
    }
}
