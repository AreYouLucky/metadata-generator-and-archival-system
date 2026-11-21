<?php

namespace Tests\Feature\Encoder;

use App\Enums\PipelineStage;
use App\Enums\Preset;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Jobs\StartProcessingJob;
use App\Models\Record;
use App\Models\User;
use App\Services\Records\PipelineRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RetryProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_record_is_reset_and_queued_again_with_the_chosen_preset(): void
    {
        Queue::fake([StartProcessingJob::class]);
        $record = $this->failedRecord();

        $this->actingAs($record->creator)
            ->postJson(route('records.retry', $record), ['preset' => 'youtube', 'custom_prompt' => ' Formal. '])
            ->assertAccepted();

        $record->refresh();
        $this->assertSame(ProcessingStatus::Queued, $record->processing_status);
        $this->assertNull($record->processing_error);
        $this->assertSame([StageStatus::Pending], $record->stages()->get()->pluck('status')->unique()->values()->all());
        $this->assertSame([null], $record->stages()->get()->pluck('message')->unique()->values()->all());
        Queue::assertPushed(
            StartProcessingJob::class,
            fn (StartProcessingJob $job): bool => $job->record->is($record)
                && $job->options->preset === Preset::Youtube
                && $job->options->customPrompt === 'Formal.',
        );
    }

    public function test_a_record_that_did_not_fail_cannot_be_retried(): void
    {
        Queue::fake([StartProcessingJob::class]);
        $record = Record::factory()->create(['processing_status' => ProcessingStatus::Processing]);

        $this->actingAs($record->creator)
            ->postJson(route('records.retry', $record), ['preset' => 'generic'])
            ->assertConflict();

        Queue::assertNothingPushed();
    }

    public function test_an_encoder_cannot_retry_someone_elses_record(): void
    {
        Queue::fake([StartProcessingJob::class]);
        $record = $this->failedRecord();

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.retry', $record), ['preset' => 'generic'])
            ->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertSame(ProcessingStatus::Failed, $record->refresh()->processing_status);
    }

    public function test_an_unknown_preset_is_rejected(): void
    {
        $record = $this->failedRecord();

        $this->actingAs($record->creator)
            ->postJson(route('records.retry', $record), ['preset' => 'myspace'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preset');
    }

    private function failedRecord(): Record
    {
        $record = Record::factory()->create();
        $record->stages()->createMany(array_map(fn (PipelineStage $stage): array => ['stage' => $stage], PipelineStage::active()));

        foreach (PipelineStage::active() as $stage) {
            app(PipelineRecorder::class)->progress($record, $stage, StageStatus::Failed, message: 'The AI service rejected the processing request.');
        }

        return $record->refresh();
    }
}
