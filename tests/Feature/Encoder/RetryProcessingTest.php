<?php

namespace Tests\Feature\Encoder;

use App\Enums\PipelineStage;
use App\Enums\Preset;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Jobs\StartProcessingJob;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordFrame;
use App\Models\RecordSegment;
use App\Models\RecordStage;
use App\Models\RecordTranscript;
use App\Models\User;
use App\Services\Records\PipelineOptions;
use App\Services\Records\PipelineRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

    public function test_a_retry_runs_the_visual_analysis_chosen_again_and_adds_the_new_stages(): void
    {
        Queue::fake([StartProcessingJob::class]);
        $record = $this->failedRecord();
        // A record from before visual analysis has no rows for those stages.
        $record->stages()->whereIn('stage', PipelineStage::visual())->delete();

        $this->actingAs($record->creator)
            ->postJson(route('records.retry', $record), ['preset' => 'generic', 'visual_analysis' => true, 'ocr' => true])
            ->assertAccepted();

        $this->assertSame(count(PipelineStage::active()), $record->stages()->count());
        Queue::assertPushed(
            StartProcessingJob::class,
            fn (StartProcessingJob $job): bool => $job->options->visualFlags() === ['visual_analysis' => true, 'ocr' => true, 'object_detection' => false],
        );
    }

    public function test_a_cancelled_record_can_be_retried(): void
    {
        Queue::fake([StartProcessingJob::class]);
        $record = $this->failedRecord();
        $record->stages()->update(['status' => StageStatus::Cancelled]);
        app(PipelineRecorder::class)->refreshStatus($record);

        $this->actingAs($record->creator)
            ->postJson(route('records.retry', $record), ['preset' => 'generic'])
            ->assertAccepted();

        $this->assertSame(ProcessingStatus::Queued, $record->refresh()->processing_status);
        Queue::assertPushed(StartProcessingJob::class);
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

    public function test_one_failed_stage_is_retried_with_the_stages_that_read_its_results(): void
    {
        Queue::fake([StartProcessingJob::class]);
        $record = $this->processedRecord(failed: PipelineStage::Transcribe);

        $this->actingAs($record->creator)
            ->postJson(route('records.retry', $record), ['preset' => 'generic', 'diarization' => true, 'stage' => 'transcribe'])
            ->assertAccepted()
            ->assertJsonPath('stages', ['transcribe', 'diarize', 'generate_metadata']);

        $statuses = $record->stages()->get()->mapWithKeys(fn (RecordStage $stage): array => [$stage->stage->value => $stage->status]);
        $this->assertSame(StageStatus::Pending, $statuses['transcribe']);
        $this->assertSame(StageStatus::Pending, $statuses['generate_metadata']);
        $this->assertSame(StageStatus::Done, $statuses['probe']);
        $this->assertSame(StageStatus::Done, $statuses['frames']);
        // The other stages are done, so the record counts as processing again.
        $this->assertSame(ProcessingStatus::Processing, $record->refresh()->processing_status);
        $this->assertTrue($record->pipeline_options['diarization']);
        Queue::assertPushed(StartProcessingJob::class, fn (StartProcessingJob $job): bool => $job->stages === [PipelineStage::Transcribe, PipelineStage::Diarize, PipelineStage::GenerateMetadata]
            && $job->options->diarization);
    }

    public function test_a_stage_whose_input_is_missing_or_that_succeeded_cannot_be_retried_alone(): void
    {
        Queue::fake([StartProcessingJob::class]);
        $record = $this->processedRecord(failed: PipelineStage::ExtractAudio);
        $record->stages()->where('stage', PipelineStage::Transcribe)->update(['status' => StageStatus::Skipped]);
        $this->actingAs($record->creator);

        $this->postJson(route('records.retry', $record), ['preset' => 'generic', 'stage' => 'transcribe'])
            ->assertConflict()->assertJsonPath('message', 'Retry the Audio extraction step first: Transcription needs its result.');
        $this->postJson(route('records.retry', $record), ['preset' => 'generic', 'stage' => 'probe'])
            ->assertConflict();

        Queue::assertNothingPushed();
    }

    public function test_a_partial_run_sends_the_stored_results_the_rerun_stages_need(): void
    {
        Http::fake(['*/jobs/process' => Http::response(['job_id' => 'run-2'], 202)]);
        $record = $this->processedRecord(failed: PipelineStage::GenerateMetadata);
        $transcript = RecordTranscript::factory()->for($record)->create(['language' => 'fil']);
        RecordSegment::factory()->for($transcript, 'transcript')->create(['position' => 1, 'start' => 1, 'end' => 2, 'text' => 'Magandang umaga.']);
        RecordFrame::factory()->for($record)->create(['time_sec' => 3, 'description' => 'A podium', 'ocr_text' => 'WELCOME']);

        StartProcessingJob::dispatchSync($record, new PipelineOptions, [PipelineStage::GenerateMetadata]);

        Http::assertSent(fn (Request $request): bool => $request['options']['stages'] === ['generate_metadata']
            && $request['seed']['language'] === 'fil'
            && $request['seed']['segments'] == [['start' => 1, 'end' => 2, 'text' => 'Magandang umaga.']]
            && $request['seed']['frames'][0]['ocr_text'] === 'WELCOME'
            && ! array_key_exists('shots', $request['seed']));
    }

    /**
     * A record that finished processing with one stage failed.
     */
    private function processedRecord(PipelineStage $failed): Record
    {
        $record = Record::factory()->create();
        RecordFile::factory()->for($record)->create();
        $record->stages()->createMany(array_map(fn (PipelineStage $stage): array => ['stage' => $stage, 'status' => StageStatus::Done, 'progress' => 100], PipelineStage::active()));
        $record->stages()->where('stage', $failed)->update(['status' => StageStatus::Failed, 'message' => 'Broken.']);
        app(PipelineRecorder::class)->refreshStatus($record);

        return $record->refresh();
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
