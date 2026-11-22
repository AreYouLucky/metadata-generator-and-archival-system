<?php

namespace Tests\Feature\Encoder;

use App\Enums\BatchStatus;
use App\Enums\PipelineStage;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Jobs\StartImportJob;
use App\Jobs\StartProcessingJob;
use App\Models\Batch;
use App\Models\Project;
use App\Models\Record;
use App\Models\User;
use App\Services\Records\PipelineRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BatchControllerTest extends TestCase
{
    use RefreshDatabase;

    private const string MP4_BYTES = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";

    public function test_uploads_and_a_web_address_become_one_batch_of_records_filed_and_processed_alike(): void
    {
        Storage::fake('media');
        Queue::fake([StartProcessingJob::class, StartImportJob::class]);
        $encoder = User::factory()->encoder()->create();
        $project = Project::factory()->create();
        $first = $this->uploadAs($encoder, 'reel_one.mp4');
        $second = $this->uploadAs($encoder, 'reel_two.mp4');

        $response = $this->postJson(route('batches.store'), [
            'name' => 'Town hall',
            'project_id' => $project->id,
            'preset' => 'generic',
            'diarization' => true,
            'items' => [
                ['upload_id' => $first],
                ['upload_id' => $second, 'title' => 'Second reel'],
                ['url' => 'https://www.youtube.com/watch?v=abc'],
            ],
        ])->assertCreated();

        $batch = Batch::findOrFail($response->json('id'));
        $response->assertJsonPath('url', route('batches.show', $batch));
        $this->assertSame('Town hall', $batch->name);
        $this->assertSame(3, $batch->total);
        $this->assertEqualsCanonicalizing(['reel one', 'Second reel', 'Import from youtube.com'], $batch->records()->pluck('title')->all());
        $this->assertSame(3, Record::query()->where('batch_id', $batch->id)->where('project_id', $project->id)->count());
        $this->assertTrue(Record::query()->where('batch_id', $batch->id)->get()->every(fn (Record $record): bool => $record->pipeline_options['diarization'] === true));
        Queue::assertPushed(StartProcessingJob::class, 2);
        Queue::assertPushed(StartImportJob::class, fn (StartImportJob $job): bool => $job->record->source_url === 'https://www.youtube.com/watch?v=abc');
    }

    public function test_one_file_that_is_not_a_video_stops_the_whole_batch_before_anything_is_created(): void
    {
        Storage::fake('media');
        Queue::fake([StartProcessingJob::class]);
        $encoder = User::factory()->encoder()->create();
        $video = $this->uploadAs($encoder, 'reel.mp4');
        $document = $this->uploadAs($encoder, 'notes.mp4', "%PDF-1.7\n%fake\n");

        $this->postJson(route('batches.store'), [
            'project_id' => Project::factory()->create()->id,
            'preset' => 'generic',
            'items' => [['upload_id' => $video], ['upload_id' => $document]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['items.1.upload_id' => 'not a video']);

        $this->assertDatabaseCount('batches', 0);
        $this->assertDatabaseCount('records', 0);
        // The good upload can still be used.
        Storage::disk('media')->assertExists("uploads/{$video}.part");
        Queue::assertNothingPushed();
    }

    public function test_a_batch_is_limited_in_size_and_refuses_private_addresses(): void
    {
        config(['archive.max_batch_size' => 2]);
        $this->actingAs(User::factory()->encoder()->create());
        $base = ['project_id' => Project::factory()->create()->id, 'preset' => 'generic'];

        $this->postJson(route('batches.store'), [...$base, 'items' => array_fill(0, 3, ['url' => 'https://vimeo.com/1'])])
            ->assertUnprocessable()->assertJsonValidationErrors(['items' => 'at most 2']);

        $this->postJson(route('batches.store'), [...$base, 'items' => [['url' => 'http://192.168.1.10/video.mp4'], ['url' => 'http://localhost/x']]])
            ->assertUnprocessable()->assertJsonValidationErrors(['items.0.url', 'items.1.url']);
    }

    public function test_the_batch_page_lists_each_records_progress_for_its_encoder_and_admins_only(): void
    {
        $batch = Batch::factory()->create(['total' => 1]);
        $record = Record::factory()->create(['batch_id' => $batch->id, 'created_by' => $batch->created_by]);
        $record->stages()->create(['stage' => PipelineStage::Probe]);

        $this->actingAs($batch->creator)
            ->get(route('batches.show', $batch))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/batches/show-page/show')
                ->where('batch.total', 1)
                ->where('records.0.id', $record->id)
                ->where('records.0.stages.0.stage', 'probe')
                ->where('records.0.zip_exclusions', ['It is still processing.']));

        $this->actingAs(User::factory()->admin()->create())->get(route('batches.show', $batch))->assertOk();
        $this->actingAs(User::factory()->encoder()->create())->get(route('batches.show', $batch))->assertForbidden();
    }

    public function test_the_batch_counts_follow_its_records_until_every_one_has_finished(): void
    {
        $batch = Batch::factory()->create(['total' => 2]);
        $records = Record::factory()->count(2)->create(['batch_id' => $batch->id, 'created_by' => $batch->created_by]);
        $recorder = app(PipelineRecorder::class);

        foreach ($records as $record) {
            $record->stages()->create(['stage' => PipelineStage::Probe]);
        }

        $recorder->complete($records[0], PipelineStage::Probe);
        $this->assertSame([1, 0, BatchStatus::Processing], [$batch->refresh()->completed, $batch->failed, $batch->status]);

        $recorder->progress($records[1], PipelineStage::Probe, StageStatus::Failed, message: 'Broken file.');
        $this->assertSame([1, 1, BatchStatus::Done], [$batch->refresh()->completed, $batch->failed, $batch->status]);
        $this->assertSame(ProcessingStatus::Failed, $records[1]->refresh()->processing_status);
    }

    private function uploadAs(User $user, string $filename, string $contents = self::MP4_BYTES): string
    {
        $id = $this->actingAs($user)
            ->postJson(route('records.uploads.store'), ['filename' => $filename, 'size' => strlen($contents)])
            ->json('id');

        $this->postJson(route('records.uploads.chunk', $id), [
            'offset' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $contents),
        ])->assertOk();

        return $id;
    }
}
