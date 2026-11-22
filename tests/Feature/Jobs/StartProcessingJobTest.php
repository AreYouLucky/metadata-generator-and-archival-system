<?php

namespace Tests\Feature\Jobs;

use App\Enums\PipelineStage;
use App\Enums\Preset;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Jobs\CancelProcessingJob;
use App\Jobs\StartProcessingJob;
use App\Models\Record;
use App\Models\RecordFile;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\PipelineOptions;
use App\Services\Records\PipelineSeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class StartProcessingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_master_and_the_preset_limits_are_sent_to_the_ai_service(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/jobs/process' => Http::response(['job_id' => 'job-1'], 202)]);
        config(['archive.max_video_minutes' => 90]);
        $file = RecordFile::factory()->create(['storage_location' => 'records/x/master/y.mp4']);

        StartProcessingJob::dispatchSync($file->record, new PipelineOptions(Preset::Youtube, 'Formal tone.'));

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Signature')
            && $request['record_id'] === $file->record_id
            && $request['record_file_id'] === $file->id
            && $request['video_path'] === 'records/x/master/y.mp4'
            && $request['options']['preset']['name'] === 'youtube'
            && $request['options']['preset']['description_max'] === 5000
            && $request['options']['custom_prompt'] === 'Formal tone.'
            && $request['options']['max_duration_seconds'] === 5400);
    }

    public function test_the_ai_services_job_id_is_kept_so_the_run_can_be_cancelled(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/jobs/process' => Http::response(['job_id' => 'run-1'], 202)]);
        $file = RecordFile::factory()->create();

        StartProcessingJob::dispatchSync($file->record, new PipelineOptions);

        $this->assertSame('run-1', $file->record->refresh()->processing_job_id);
    }

    public function test_a_record_cancelled_while_the_job_waited_is_not_sent(): void
    {
        Http::preventStrayRequests();
        $file = RecordFile::factory()->create();
        $file->record->forceFill(['processing_status' => ProcessingStatus::Cancelled])->save();

        StartProcessingJob::dispatchSync($file->record, new PipelineOptions);

        Http::assertNothingSent();
    }

    public function test_a_cancel_that_arrived_while_the_request_was_in_flight_is_passed_on(): void
    {
        Queue::fake([CancelProcessingJob::class]);
        Http::preventStrayRequests();
        $file = RecordFile::factory()->create();
        Http::fake(['http://ai-api.test/jobs/process' => function () use ($file) {
            $file->record->forceFill(['processing_status' => ProcessingStatus::Cancelled])->save();

            return Http::response(['job_id' => 'run-1'], 202);
        }]);

        (new StartProcessingJob($file->record, new PipelineOptions))->handle(app(AiServiceClient::class), app(PipelineSeed::class));

        $this->assertNull($file->record->refresh()->processing_job_id);
        Queue::assertPushed(CancelProcessingJob::class, fn (CancelProcessingJob $job): bool => $job->jobId === 'run-1');
    }

    public function test_ocr_and_objects_are_only_asked_for_with_visual_analysis(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/jobs/process' => Http::response(['job_id' => 'job-1'], 202)]);
        $on = RecordFile::factory()->create();
        $off = RecordFile::factory()->create();

        StartProcessingJob::dispatchSync($on->record, new PipelineOptions(visualAnalysis: true, ocr: true, objectDetection: false));
        StartProcessingJob::dispatchSync($off->record, new PipelineOptions(visualAnalysis: false, ocr: true, objectDetection: true));

        $flags = fn (Request $request): array => [$request['options']['visual_analysis'], $request['options']['ocr'], $request['options']['object_detection']];
        Http::assertSent(fn (Request $request): bool => $request['record_id'] === $on->record_id && $flags($request) === [true, true, false]);
        Http::assertSent(fn (Request $request): bool => $request['record_id'] === $off->record_id && $flags($request) === [false, false, false]);
    }

    public function test_a_rejected_request_fails_every_stage_at_once_instead_of_retrying(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/jobs/process' => Http::response(['detail' => 'invalid'], 422)]);
        $file = RecordFile::factory()->create();
        $file->record->stages()->createMany(array_map(fn (PipelineStage $stage): array => ['stage' => $stage], PipelineStage::active()));

        StartProcessingJob::dispatchSync($file->record, new PipelineOptions);

        Http::assertSentCount(1);
        $this->assertSame(
            ['The AI service rejected the processing request.'],
            $file->record->stages()->get()->pluck('message')->unique()->values()->all(),
        );
    }

    public function test_every_stage_fails_when_the_job_gives_up_so_the_record_does_not_hang(): void
    {
        $record = Record::factory()->create();
        $record->stages()->createMany(array_map(fn (PipelineStage $stage): array => ['stage' => $stage], PipelineStage::active()));

        (new StartProcessingJob($record, new PipelineOptions))->failed(new RuntimeException('connection refused'));

        $this->assertSame(
            [StageStatus::Failed],
            $record->stages()->get()->pluck('status')->unique()->values()->all(),
        );
        $this->assertSame(ProcessingStatus::Failed, $record->refresh()->processing_status);
    }
}
