<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Enums\FileRole;
use App\Enums\PipelineStage;
use App\Enums\Preset;
use App\Enums\ProcessingStatus;
use App\Jobs\StartProcessingJob;
use App\Models\ProductionAgency;
use App\Models\Project;
use App\Models\ProjectSeries;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecordControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The smallest MP4 header libmagic recognises as video/mp4.
     */
    private const string MP4_BYTES = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";

    public function test_a_finished_upload_becomes_a_record_with_its_master_file_stages_and_audit_entry(): void
    {
        Storage::fake('media');
        Queue::fake([StartProcessingJob::class]);
        $encoder = User::factory()->encoder()->create();
        $uploadId = $this->uploadAs($encoder, 'Opening_ceremony.mp4', self::MP4_BYTES);
        $series = ProjectSeries::factory()->create();

        $response = $this->postJson(route('records.store'), [
            'upload_id' => $uploadId,
            'project_id' => $series->project_id,
            'project_series_id' => $series->id,
            'preset' => 'youtube',
            'custom_prompt' => ' Write in Filipino. ',
            'expected_sha256' => str_repeat('A', 64),
        ])->assertCreated();

        $record = Record::findOrFail($response->json('id'));
        $file = $record->files()->sole();

        $response->assertJsonPath('url', route('records.show', $record));
        $this->assertSame('Opening ceremony', $record->title);
        $this->assertSame($encoder->id, $record->created_by);
        $this->assertSame(ProcessingStatus::Queued, $record->processing_status);
        $this->assertSame($series->project_id, $record->project_id);
        $this->assertSame($series->id, $record->project_series_id);
        $this->assertSame(FileRole::Master, $file->role);
        $this->assertSame('video/mp4', $file->mime_type);
        $this->assertSame('Opening_ceremony.mp4', $file->original_filename);
        $this->assertSame(str_repeat('a', 64), $file->expected_sha256);
        Storage::disk('media')->assertExists($file->storage_location);
        Storage::disk('media')->assertMissing("uploads/{$uploadId}.part");
        $this->assertSame(
            array_map(fn (PipelineStage $stage): string => $stage->value, PipelineStage::active()),
            $record->stages()->orderBy('id')->pluck('stage')->map->value->all(),
        );
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'record_file_id' => $file->id,
            'action' => AuditAction::Uploaded->value,
            'performed_by' => $encoder->id,
        ]);
        Queue::assertPushed(
            StartProcessingJob::class,
            fn (StartProcessingJob $job): bool => $job->record->is($record)
                && $job->options->preset === Preset::Youtube
                && $job->options->customPrompt === 'Write in Filipino.',
        );
    }

    public function test_a_file_that_is_not_really_a_video_is_rejected_and_discarded(): void
    {
        Storage::fake('media');
        Queue::fake([StartProcessingJob::class]);
        $encoder = User::factory()->encoder()->create();
        $uploadId = $this->uploadAs($encoder, 'renamed.mp4', "%PDF-1.7\n%fake document\n");

        $this->postJson(route('records.store'), ['upload_id' => $uploadId, 'project_id' => Project::factory()->create()->id, 'preset' => 'generic'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['upload_id' => 'The file is not a video']);

        $this->assertDatabaseCount('records', 0);
        Storage::disk('media')->assertMissing("uploads/{$uploadId}.part");
        Queue::assertNotPushed(StartProcessingJob::class);
    }

    public function test_an_unfinished_upload_cannot_become_a_record(): void
    {
        Storage::fake('media');
        $encoder = User::factory()->encoder()->create();
        $uploadId = $this->actingAs($encoder)
            ->postJson(route('records.uploads.store'), ['filename' => 'opening.mp4', 'size' => 100])
            ->json('id');

        $this->postJson(route('records.store'), ['upload_id' => $uploadId, 'project_id' => Project::factory()->create()->id, 'preset' => 'generic'])->assertConflict();

        $this->assertDatabaseCount('records', 0);
    }

    public function test_a_record_must_be_filed_under_a_project_and_keeps_the_upload_for_a_retry(): void
    {
        Storage::fake('media');
        $encoder = User::factory()->encoder()->create();
        $uploadId = $this->uploadAs($encoder, 'opening.mp4', self::MP4_BYTES);

        $this->postJson(route('records.store'), ['upload_id' => $uploadId, 'preset' => 'generic'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id' => 'Choose the project (event) this video belongs to.']);

        Storage::disk('media')->assertExists("uploads/{$uploadId}.part");
    }

    public function test_a_series_from_another_project_is_refused(): void
    {
        $foreignSeries = ProjectSeries::factory()->create();

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.store'), [
                'upload_id' => fake()->uuid(),
                'project_id' => Project::factory()->create()->id,
                'project_series_id' => $foreignSeries->id,
                'preset' => 'generic',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_series_id' => 'That series does not belong to the chosen project.']);
    }

    public function test_a_production_unit_must_belong_to_the_chosen_agency(): void
    {
        $agency = ProductionAgency::factory()->create();
        $foreignUnit = ProductionAgency::factory()->create(['parent_id' => ProductionAgency::factory()->create()->id]);

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.store'), [
                'upload_id' => fake()->uuid(),
                'project_id' => Project::factory()->create()->id,
                'agency_id' => $agency->id,
                'division_id' => $foreignUnit->id,
                'preset' => 'generic',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['division_id' => 'That production unit does not belong to the chosen agency.']);
    }

    public function test_a_preset_that_is_not_enabled_is_refused(): void
    {
        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.store'), ['upload_id' => fake()->uuid(), 'preset' => 'tiktok'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preset']);
    }

    public function test_the_import_page_preselects_a_project_from_the_query_string(): void
    {
        $series = ProjectSeries::factory()->create(['title' => 'Day 1']);

        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('records.create', ['project' => $series->project_id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/records/create-page/create')
                ->where('initialProject.id', $series->project_id)
                ->where('initialProject.series.0.title', 'Day 1'));

        $this->get(route('records.create', ['project' => 'not-a-project']))->assertSessionHasErrors('project');
    }

    public function test_the_import_page_preselects_a_series_only_from_the_same_project(): void
    {
        $series = ProjectSeries::factory()->create();
        $otherSeries = ProjectSeries::factory()->create();

        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('records.create', ['project' => $series->project_id, 'series' => $series->id]))
            ->assertInertia(fn (Assert $page) => $page->where('initialSeriesId', $series->id));

        $this->get(route('records.create', ['project' => $series->project_id, 'series' => $otherSeries->id]))
            ->assertSessionHasErrors('series');
    }

    public function test_the_history_shows_an_encoder_only_their_own_records(): void
    {
        $encoder = User::factory()->encoder()->create();
        $own = Record::factory()->for($encoder, 'creator')->create();
        Record::factory()->create();

        $this->actingAs($encoder)
            ->get(route('records.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/dashboard-page/dashboard')
                ->where('showCreator', false)
                ->has('records.data', 1)
                ->where('records.data.0.id', $own->id));
    }

    public function test_the_history_shows_admins_every_record_and_filters_by_status(): void
    {
        $ready = Record::factory()->create(['processing_status' => ProcessingStatus::Ready]);
        Record::factory()->create(['processing_status' => ProcessingStatus::Failed]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('records.index', ['processing_status' => 'ready']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('showCreator', true)
                ->has('records.data', 1)
                ->where('records.data.0.id', $ready->id)
                ->where('records.data.0.creator', $ready->creator->name));
    }

    public function test_an_unknown_status_filter_is_rejected(): void
    {
        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('records.index', ['processing_status' => 'exploded']))
            ->assertSessionHasErrors('processing_status');
    }

    public function test_the_workspace_renders_for_the_owner(): void
    {
        $record = Record::factory()->create();

        $this->actingAs($record->creator)
            ->get(route('records.show', $record))
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/records/show-page/show')
                ->where('record.id', $record->id)
                ->where('record.can_edit', true)
                ->where('transcript', null)
                ->has('presets', 2));
    }

    public function test_an_encoder_cannot_open_another_encoders_record(): void
    {
        $record = Record::factory()->create();

        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('records.show', $record))
            ->assertForbidden();
    }

    public function test_the_master_streams_only_to_someone_who_may_view_the_record(): void
    {
        Storage::fake('media');
        $file = RecordFile::factory()->create();
        Storage::disk('media')->put($file->storage_location, self::MP4_BYTES);

        $this->actingAs($file->record->creator)
            ->get(route('records.media', $file->record))
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4');

        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('records.media', $file->record))
            ->assertForbidden();
    }

    private function uploadAs(User $user, string $filename, string $contents): string
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
