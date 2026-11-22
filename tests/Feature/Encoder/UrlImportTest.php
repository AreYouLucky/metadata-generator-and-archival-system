<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Enums\FileRole;
use App\Enums\PipelineStage;
use App\Enums\ProcessingStatus;
use App\Enums\SourceCarrier;
use App\Enums\StageStatus;
use App\Jobs\StartImportJob;
use App\Jobs\StartProcessingJob;
use App\Models\Project;
use App\Models\Record;
use App\Models\User;
use App\Services\InternalSignature;
use App\Services\Records\PipelineOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UrlImportTest extends TestCase
{
    use RefreshDatabase;

    private const string MP4_BYTES = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";

    public function test_a_web_address_creates_a_record_waiting_for_its_download(): void
    {
        Queue::fake([StartImportJob::class]);
        $encoder = User::factory()->encoder()->create();

        $response = $this->actingAs($encoder)->postJson(route('records.store'), [
            'url' => 'https://www.youtube.com/watch?v=abc',
            'project_id' => Project::factory()->create()->id,
            'preset' => 'youtube',
            'visual_analysis' => true,
        ])->assertCreated();

        $record = Record::findOrFail($response->json('id'));
        $this->assertSame('Import from youtube.com', $record->title);
        $this->assertSame('https://www.youtube.com/watch?v=abc', $record->source_url);
        $this->assertSame(0, $record->files()->count());
        $this->assertSame('import', $record->stages()->orderBy('id')->first()?->stage->value);
        $this->assertSame(['preset' => 'youtube', 'custom_prompt' => null, 'visual_analysis' => true, 'ocr' => false, 'object_detection' => false, 'diarization' => false], $record->pipeline_options);
        Queue::assertPushed(StartImportJob::class, fn (StartImportJob $job): bool => $job->record->is($record));
    }

    public function test_local_and_private_addresses_are_refused(): void
    {
        $this->actingAs(User::factory()->encoder()->create());
        $base = ['project_id' => Project::factory()->create()->id, 'preset' => 'generic'];

        foreach (['http://localhost/video.mp4', 'http://10.0.0.4/v', 'ftp://example.com/v.mp4', 'https://user:pw@example.com/v', 'https://example.com:8443/v', 'http://nas.local/v'] as $url) {
            $this->postJson(route('records.store'), [...$base, 'url' => $url])->assertUnprocessable()->assertJsonValidationErrors('url');
        }

        $this->postJson(route('records.store'), [...$base, 'url' => 'https://vimeo.com/1', 'upload_id' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('upload_id');
        $this->assertDatabaseCount('records', 0);
    }

    public function test_the_import_job_asks_the_ai_service_for_the_download_and_keeps_the_job_id(): void
    {
        Http::fake(['*/import-url' => Http::response(['job_id' => 'run-7'], 202)]);
        $record = Record::factory()->create();
        $record->forceFill(['source_url' => 'https://vimeo.com/1'])->save();

        StartImportJob::dispatchSync($record);

        Http::assertSent(fn (Request $request): bool => $request['url'] === 'https://vimeo.com/1'
            && $request['output_dir'] === "records/{$record->id}/master"
            && $request['max_bytes'] === config('archive.max_upload_mb') * 1024 * 1024
            && Str::isUlid($request['record_file_id']));
        $this->assertSame('run-7', $record->refresh()->processing_job_id);
    }

    public function test_a_finished_download_becomes_the_master_and_processing_starts_with_the_chosen_options(): void
    {
        Storage::fake('media');
        Queue::fake([StartProcessingJob::class]);
        $record = $this->importing();
        $fileId = strtolower((string) Str::ulid());
        $path = "records/{$record->id}/master/{$fileId}.mp4";
        Storage::disk('media')->put($path, self::MP4_BYTES);

        $this->postSigned("/internal/imports/{$record->id}/done", [
            'status' => 'done',
            'path' => $path,
            'file_size' => strlen(self::MP4_BYTES),
            'source' => ['title' => 'Town hall meeting', 'uploader' => 'LGU Cebu', 'upload_date' => '20261101', 'extractor' => 'Youtube', 'webpage_url' => 'https://www.youtube.com/watch?v=abc'],
        ])->assertOk();

        $record->refresh();
        $master = $record->files()->sole();
        $this->assertSame(FileRole::Master, $master->role);
        $this->assertSame($fileId, $master->id);
        $this->assertSame('video/mp4', $master->mime_type);
        $this->assertSame('Town hall meeting', $record->title);
        $this->assertSame(StageStatus::Done, $record->stages()->where('stage', PipelineStage::Import)->sole()->status);
        $this->assertSame(SourceCarrier::BornDigital, $record->provenance?->source_carrier);
        $this->assertStringContainsString('Imported from https://www.youtube.com/watch?v=abc (Youtube). Title on the site: "Town hall meeting". Uploaded by LGU Cebu on 2026-11-01.', (string) $record->provenance?->notes);
        $this->assertDatabaseHas('video_audit_logs', ['record_id' => $record->id, 'record_file_id' => $fileId, 'action' => AuditAction::Uploaded->value]);
        Queue::assertPushed(StartProcessingJob::class, fn (StartProcessingJob $job): bool => $job->options->visualAnalysis && $job->stages === null);
    }

    public function test_a_failed_download_fails_the_record_with_the_reason(): void
    {
        $record = $this->importing();

        $this->postSigned("/internal/imports/{$record->id}/done", ['status' => 'failed', 'error' => 'The video could not be downloaded: Video unavailable'])->assertOk();

        $record->refresh();
        $this->assertSame(ProcessingStatus::Failed, $record->processing_status);
        $this->assertSame('Download from URL: The video could not be downloaded: Video unavailable', $record->processing_error);
        $this->assertSame([StageStatus::Skipped], $record->stages()->where('stage', '!=', PipelineStage::Import)->pluck('status')->unique()->values()->all());
    }

    public function test_a_file_outside_the_records_master_folder_is_refused(): void
    {
        $record = $this->importing();
        $other = Record::factory()->create();

        $this->postSigned("/internal/imports/{$record->id}/done", [
            'status' => 'done',
            'path' => "records/{$other->id}/master/".Str::ulid().'.mp4',
            'file_size' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('path');

        $this->assertSame(0, $record->files()->count());
    }

    public function test_a_failed_import_is_retried_by_downloading_again(): void
    {
        Queue::fake([StartImportJob::class, StartProcessingJob::class]);
        $record = $this->importing();
        $this->postSigned("/internal/imports/{$record->id}/done", ['status' => 'failed', 'error' => 'Timed out.'])->assertOk();

        $this->actingAs($record->creator)
            ->postJson(route('records.retry', $record), ['preset' => 'generic'])
            ->assertAccepted();

        $this->assertSame(ProcessingStatus::Queued, $record->refresh()->processing_status);
        $this->assertSame([StageStatus::Pending], $record->stages()->pluck('status')->unique()->values()->all());
        Queue::assertPushed(StartImportJob::class);
        Queue::assertNotPushed(StartProcessingJob::class);
    }

    private function importing(): Record
    {
        $record = Record::factory()->create();
        $record->forceFill([
            'source_url' => 'https://www.youtube.com/watch?v=abc',
            'title' => 'Import from youtube.com',
            'pipeline_options' => (new PipelineOptions(visualAnalysis: true))->toArray(),
        ])->save();
        $record->stages()->createMany(array_map(fn (PipelineStage $stage): array => ['stage' => $stage], PipelineStage::tracked()));

        return $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSigned(string $uri, array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars([
            ...app(InternalSignature::class)->headers($body),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]), $body);
    }
}
