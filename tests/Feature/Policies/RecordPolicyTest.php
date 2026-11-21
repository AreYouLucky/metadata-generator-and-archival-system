<?php

namespace Tests\Feature\Policies;

use App\Enums\AccessLevel;
use App\Enums\PermissionStatus;
use App\Enums\ReviewStatus;
use App\Enums\SecurityClassification;
use App\Models\Record;
use App\Models\RecordRights;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_an_approved_published_unclassified_public_record_may_be_distributed_even_to_guests(): void
    {
        $record = $this->distributable();

        $this->assertTrue(Gate::forUser(null)->allows('distribute', $record));
    }

    /**
     * @param  Closure(Record): void  $spoil
     */
    #[DataProvider('blockers')]
    public function test_distribution_is_refused(Closure $spoil): void
    {
        $record = $this->distributable();
        $spoil($record);

        $this->assertFalse(Gate::forUser(null)->allows('distribute', $record->refresh()));
    }

    /**
     * @return array<string, array{Closure(Record): void}>
     */
    public static function blockers(): array
    {
        return [
            'not approved' => [fn (Record $record) => $record->forceFill(['review_status' => ReviewStatus::Submitted])->save()],
            'not published' => [fn (Record $record) => $record->forceFill(['published_at' => null])->save()],
            'deleted' => [fn (Record $record) => $record->delete()],
            'classified' => [fn (Record $record) => $record->forceFill(['security_classification' => SecurityClassification::Restricted])->save()],
            'no rights' => [fn (Record $record) => $record->rights()->delete()],
            'internal access' => [fn (Record $record) => $record->rights()->update(['access_level' => AccessLevel::Internal])],
            'permission pending' => [fn (Record $record) => $record->rights()->update(['permission_status' => PermissionStatus::Pending])],
            'embargo in force' => [fn (Record $record) => $record->rights()->update(['embargo_until' => now()->addDay()])],
        ];
    }

    private function distributable(): Record
    {
        $record = Record::factory()->create();
        $record->forceFill(['review_status' => ReviewStatus::Approved, 'published_at' => now()->subDay()])->save();
        RecordRights::factory()->for($record)->create([
            'access_level' => AccessLevel::Public,
            'permission_status' => PermissionStatus::Approved,
            'embargo_until' => now()->subDay(),
        ]);

        return $record;
    }
}
