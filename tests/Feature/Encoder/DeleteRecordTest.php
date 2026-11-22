<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Enums\PipelineStage;
use App\Enums\ProcessingStatus;
use App\Jobs\CancelProcessingJob;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeleteRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_encoder_soft_deletes_their_draft_and_the_deletion_is_logged(): void
    {
        $record = Record::factory()->create(['processing_status' => ProcessingStatus::Ready]);

        $this->actingAs($record->creator)
            ->deleteJson(route('records.destroy', $record))
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertSoftDeleted($record);
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::Deleted->value,
            'performed_by' => $record->created_by,
        ]);
        $this->actingAs($record->creator)->get(route('records.show', $record))->assertNotFound();
    }

    public function test_deleting_a_record_that_is_still_processing_cancels_it_first(): void
    {
        Queue::fake([CancelProcessingJob::class]);
        $record = Record::factory()->create(['processing_status' => ProcessingStatus::Processing]);
        $record->forceFill(['processing_job_id' => 'run-1'])->save();
        $record->stages()->createMany(array_map(fn (PipelineStage $stage): array => ['stage' => $stage], PipelineStage::active()));

        $this->actingAs($record->creator)->deleteJson(route('records.destroy', $record))->assertOk();

        $this->assertSoftDeleted($record);
        $this->assertSame(ProcessingStatus::Cancelled, Record::withTrashed()->find($record->id)?->processing_status);
        Queue::assertPushed(CancelProcessingJob::class, fn (CancelProcessingJob $job): bool => $job->jobId === 'run-1');
    }

    public function test_an_encoder_cannot_delete_a_record_once_it_is_submitted(): void
    {
        $record = Record::factory()->submitted()->create();

        $this->actingAs($record->creator)
            ->deleteJson(route('records.destroy', $record))
            ->assertForbidden();

        $this->assertNotSoftDeleted($record);
    }

    public function test_an_encoder_cannot_delete_someone_elses_record(): void
    {
        $record = Record::factory()->create();

        $this->actingAs(User::factory()->encoder()->create())
            ->deleteJson(route('records.destroy', $record))
            ->assertForbidden();

        $this->assertNotSoftDeleted($record);
    }

    public function test_an_admin_can_delete_an_approved_record(): void
    {
        $record = Record::factory()->approved()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson(route('records.destroy', $record))
            ->assertOk();

        $this->assertSoftDeleted($record);
    }
}
