<?php

namespace Tests\Feature\Jobs;

use App\Enums\PipelineStage;
use App\Enums\Preset;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Jobs\StartProcessingJob;
use App\Models\Record;
use App\Models\RecordFile;
use App\Services\Records\PipelineOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
