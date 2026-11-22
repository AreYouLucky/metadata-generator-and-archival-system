<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Enums\PipelineStage;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Jobs\CancelProcessingJob;
use App\Models\Record;
use App\Models\User;
use App\Services\Records\PipelineRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CancelProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unfinished_stages_are_cancelled_finished_ones_kept_and_the_ai_service_told(): void
    {
        Queue::fake([CancelProcessingJob::class]);
        $record = $this->processingRecord('run-1');

        $this->actingAs($record->creator)
            ->postJson(route('records.cancel', $record))
            ->assertOk()
            ->assertJson(['cancelled' => true]);

        $record->refresh();
        $statuses = $record->stages()->get()->mapWithKeys(fn ($stage): array => [$stage->stage->value => $stage->status]);
        $this->assertSame(ProcessingStatus::Cancelled, $record->processing_status);
        $this->assertNull($record->processing_error);
        $this->assertSame(StageStatus::Done, $statuses['checksum']);
        $this->assertSame(StageStatus::Cancelled, $statuses['transcribe']);
        $this->assertSame(StageStatus::Cancelled, $statuses['generate_metadata']);
        $this->assertSame("Cancelled by {$record->creator->name}.", $record->stages()->where('stage', PipelineStage::Transcribe)->value('message'));
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::Updated->value,
            'performed_by' => $record->created_by,
            'remarks' => 'Processing cancelled.',
        ]);
        Queue::assertPushed(CancelProcessingJob::class, fn (CancelProcessingJob $job): bool => $job->recordId === $record->id && $job->jobId === 'run-1');
    }

    public function test_a_record_still_waiting_to_start_is_cancelled_without_calling_the_ai_service(): void
    {
        Queue::fake([CancelProcessingJob::class]);
        $record = $this->processingRecord(jobId: null, started: false);

        $this->actingAs($record->creator)->postJson(route('records.cancel', $record))->assertOk();

        $this->assertSame(ProcessingStatus::Cancelled, $record->refresh()->processing_status);
        Queue::assertNotPushed(CancelProcessingJob::class);
    }

    public function test_a_finished_record_cannot_be_cancelled(): void
    {
        Queue::fake([CancelProcessingJob::class]);
        $record = Record::factory()->create(['processing_status' => ProcessingStatus::Ready]);

        $this->actingAs($record->creator)
            ->postJson(route('records.cancel', $record))
            ->assertConflict();

        $this->assertSame(ProcessingStatus::Ready, $record->refresh()->processing_status);
        Queue::assertNotPushed(CancelProcessingJob::class);
    }

    public function test_an_encoder_cannot_cancel_someone_elses_record(): void
    {
        Queue::fake([CancelProcessingJob::class]);
        $record = $this->processingRecord('run-1');

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.cancel', $record))
            ->assertForbidden();

        $this->assertSame(ProcessingStatus::Processing, $record->refresh()->processing_status);
        Queue::assertNotPushed(CancelProcessingJob::class);
    }

    public function test_the_workspace_tells_the_page_who_may_cancel_and_delete(): void
    {
        $draft = Record::factory()->create();
        $submitted = Record::factory()->submitted()->create();

        $this->actingAs($draft->creator)->get(route('records.show', $draft))
            ->assertInertia(fn ($page) => $page->where('record.can_cancel', true)->where('record.can_delete', true));
        $this->actingAs($submitted->creator)->get(route('records.show', $submitted))
            ->assertInertia(fn ($page) => $page->where('record.can_cancel', false)->where('record.can_delete', false));
    }

    private function processingRecord(?string $jobId, bool $started = true): Record
    {
        $record = Record::factory()->create();
        $record->forceFill(['processing_job_id' => $jobId])->save();
        $record->stages()->createMany(array_map(fn (PipelineStage $stage): array => ['stage' => $stage], PipelineStage::active()));

        if ($started) {
            $recorder = app(PipelineRecorder::class);
            $recorder->complete($record, PipelineStage::Checksum);
            $recorder->progress($record, PipelineStage::Transcribe, StageStatus::Running, 40);
        }

        return $record->refresh();
    }
}
