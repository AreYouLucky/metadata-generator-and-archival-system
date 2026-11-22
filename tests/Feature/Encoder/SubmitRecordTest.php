<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Enums\ProcessingStatus;
use App\Enums\ReviewStatus;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubmitRecordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{ReviewStatus}>
     */
    public static function editableStatuses(): array
    {
        return [
            'draft' => [ReviewStatus::Draft],
            'changes requested' => [ReviewStatus::ChangesRequested],
        ];
    }

    #[DataProvider('editableStatuses')]
    public function test_the_encoder_submits_a_finished_record_with_a_note(ReviewStatus $from): void
    {
        $record = Record::factory()->create(['processing_status' => ProcessingStatus::Ready, 'review_status' => $from]);

        $this->actingAs($record->creator)
            ->postJson(route('records.submit', $record), ['notes' => ' Fixed the title. '])
            ->assertOk()
            ->assertJsonPath('review_status', 'submitted');

        $record->refresh();
        $this->assertSame(ReviewStatus::Submitted, $record->review_status);
        $this->assertNotNull($record->submitted_at);
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::Submitted->value,
            'performed_by' => $record->created_by,
            'remarks' => 'Fixed the title.',
        ]);
        $this->assertFalse($record->creator->can('update', $record));
    }

    public function test_a_record_still_processing_cannot_be_submitted(): void
    {
        $record = Record::factory()->create(['processing_status' => ProcessingStatus::Processing]);

        $this->actingAs($record->creator)
            ->postJson(route('records.submit', $record))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Wait until processing has finished before submitting this record.');

        $this->assertSame(ReviewStatus::Draft, $record->refresh()->review_status);
    }

    public function test_a_submitted_or_approved_record_cannot_be_submitted_again(): void
    {
        $record = Record::factory()->approved()->create();

        $this->actingAs($record->creator)
            ->postJson(route('records.submit', $record))
            ->assertForbidden();

        $this->assertSame(ReviewStatus::Approved, $record->refresh()->review_status);
    }

    public function test_another_encoder_cannot_submit_the_record(): void
    {
        $record = Record::factory()->create(['processing_status' => ProcessingStatus::Ready]);

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.submit', $record))
            ->assertForbidden();
    }

    public function test_the_workspace_tells_the_encoder_they_can_submit_and_shows_review_notes(): void
    {
        $record = Record::factory()->create(['processing_status' => ProcessingStatus::Ready, 'review_status' => ReviewStatus::ChangesRequested]);
        $record->auditLogs()->create([
            'action' => AuditAction::ChangesRequested,
            'performed_by' => User::factory()->admin()->create()->id,
            'performed_at' => now(),
            'remarks' => 'Add the speaker names.',
        ]);

        $this->actingAs($record->creator)
            ->get(route('records.show', $record))
            ->assertInertia(fn ($page) => $page
                ->where('review.can_submit', true)
                ->where('review.can_review', false)
                ->where('review.can_reopen', false)
                ->where('review.is_public', false)
                ->where('review.history.0.action', 'changes_requested')
                ->where('review.history.0.notes', 'Add the speaker names.'));
    }
}
