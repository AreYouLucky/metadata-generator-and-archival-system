<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Events\RecordAudioExportUpdated;
use App\Jobs\StartAudioExportJob;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\User;
use App\Services\InternalSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AudioExportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_preparing_an_export_queues_it_once_and_the_workspace_shows_it_in_progress(): void
    {
        Storage::fake('media');
        Queue::fake([StartAudioExportJob::class]);
        $record = $this->recordWithAudio();

        $this->actingAs($record->creator)
            ->postJson(route('records.audio-export.store', $record), ['format' => 'mp3'])
            ->assertAccepted()
            ->assertJsonPath('status', 'processing');
        $this->postJson(route('records.audio-export.store', $record), ['format' => 'mp3'])->assertAccepted();

        Queue::assertPushed(StartAudioExportJob::class, 1);
        Queue::assertPushed(StartAudioExportJob::class, fn (StartAudioExportJob $job): bool => $job->record->is($record) && $job->format === 'mp3');
        $this->get(route('records.show', $record))->assertInertia(fn (Assert $page) => $page
            ->where('audioExports.0.format', 'mp3')
            ->where('audioExports.0.status', 'processing')
            ->where('audioExports.1.status', 'none'));
    }

    public function test_a_finished_export_is_reused_and_downloaded_with_an_audit_entry(): void
    {
        Storage::fake('media');
        Queue::fake([StartAudioExportJob::class]);
        $record = $this->recordWithAudio(['title' => 'Opening Ceremony']);
        Storage::disk('media')->put("records/{$record->id}/exports/audio.wav", 'RIFF');

        $this->actingAs($record->creator)
            ->postJson(route('records.audio-export.store', $record), ['format' => 'wav'])
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('exports.1.download_url', route('records.audio-export.show', [$record, 'wav']));
        $this->get(route('records.audio-export.show', [$record, 'wav']))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/wav')
            ->assertDownload('opening-ceremony-'.strtolower($record->id).'.wav');

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('video_audit_logs', ['record_id' => $record->id, 'action' => AuditAction::Exported->value, 'remarks' => 'Format: wav (audio)']);
    }

    public function test_a_video_without_audio_or_an_unknown_format_is_refused(): void
    {
        Queue::fake([StartAudioExportJob::class]);
        $silent = Record::factory()->create();
        RecordFile::factory()->for($silent)->create()->technicalMetadata()->create(['audio_codec' => null]);

        $this->actingAs($silent->creator)
            ->postJson(route('records.audio-export.store', $silent), ['format' => 'mp3'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format' => 'The video has no audio track.']);
        $this->postJson(route('records.audio-export.store', $silent), ['format' => 'flac'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);
        $this->get(route('records.audio-export.show', [$silent, 'flac']))->assertSessionHasErrors('format');

        Queue::assertNothingPushed();
    }

    public function test_another_encoder_cannot_prepare_or_download_the_audio(): void
    {
        Storage::fake('media');
        $record = $this->recordWithAudio();
        Storage::disk('media')->put("records/{$record->id}/exports/audio.mp3", 'ID3');
        $stranger = User::factory()->encoder()->create();

        $this->actingAs($stranger)->postJson(route('records.audio-export.store', $record), ['format' => 'mp3'])->assertForbidden();
        $this->actingAs($stranger)->get(route('records.audio-export.show', [$record, 'mp3']))->assertForbidden();
    }

    public function test_the_ai_service_is_asked_for_the_masters_audio_at_the_records_export_path(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/jobs/export-audio' => Http::response(['job_id' => 'job-1'], 202)]);
        $record = $this->recordWithAudio();

        StartAudioExportJob::dispatchSync($record, 'mp3');

        Http::assertSent(fn (Request $request): bool => $request['video_path'] === $record->masterFile->storage_location
            && $request['format'] === 'mp3'
            && $request['output_path'] === "records/{$record->id}/exports/audio.mp3"
            && (float) $request['duration'] === 60.0);
    }

    public function test_the_callback_reports_a_finished_export_and_a_failure_can_be_retried(): void
    {
        Queue::fake([StartAudioExportJob::class]);
        Event::fake([RecordAudioExportUpdated::class]);
        $record = $this->recordWithAudio();
        $this->actingAs($record->creator)->postJson(route('records.audio-export.store', $record), ['format' => 'mp3'])->assertAccepted();

        $this->postSigned("/internal/records/{$record->id}/audio-export", ['format' => 'mp3', 'error' => 'The video has no audio track.'])->assertOk();

        Event::assertDispatched(RecordAudioExportUpdated::class, fn (RecordAudioExportUpdated $event): bool => $event->status === 'failed' && $event->error === 'The video has no audio track.');
        $this->get(route('records.show', $record))->assertInertia(fn (Assert $page) => $page
            ->where('audioExports.0.status', 'failed')
            ->where('audioExports.0.error', 'The video has no audio track.'));
        $this->postJson(route('records.audio-export.store', $record), ['format' => 'mp3'])->assertAccepted();
        Queue::assertPushed(StartAudioExportJob::class, 2);
    }

    public function test_a_callback_path_outside_the_records_export_is_refused(): void
    {
        $record = $this->recordWithAudio();

        $this->postSigned("/internal/records/{$record->id}/audio-export", ['format' => 'mp3', 'path' => 'records/other/exports/audio.mp3', 'file_size' => 10])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['path']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordWithAudio(array $attributes = []): Record
    {
        $record = Record::factory()->create($attributes);
        RecordFile::factory()->for($record)->create()->technicalMetadata()->create(['audio_codec' => 'aac', 'duration' => 60]);

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
